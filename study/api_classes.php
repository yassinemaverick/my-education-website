<?php
// Suppress any stray PHP notices/warnings so they don't corrupt JSON output
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';

function jsonOut(array $data): void {
  ob_end_clean(); // discard any accidental output before JSON
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function err(string $msg, int $code=400): void { http_response_code($code); jsonOut(['ok'=>false,'error'=>$msg]); }

// Auth
$role   = $_SESSION['role']  ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) err('Non authentifié', 401);

require_once __DIR__ . '/rate_limit.php';
api_rate_limit('classes:' . $userId, 60, 60);

// CSRF for POST — delegates to the shared csrf.php implementation
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

$pdo = db();

// ── Auto-create tables (no FOREIGN KEY to avoid privilege issues on shared hosting) ──
$pdo->exec("CREATE TABLE IF NOT EXISTS class_groups (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  type_key     VARCHAR(50)  NOT NULL,
  level_number TINYINT      NULL,
  group_letter VARCHAR(10)  NOT NULL,
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_class_group (type_key, level_number, group_letter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add columns if they don't exist yet
try { $pdo->exec("ALTER TABLE class_groups ADD COLUMN schedule_json TEXT DEFAULT NULL"); } catch(Throwable $e) {}
try { $pdo->exec("ALTER TABLE class_groups ADD COLUMN zoom_url VARCHAR(500) DEFAULT NULL"); } catch(Throwable $e) {}
try { $pdo->exec("ALTER TABLE class_groups ADD COLUMN start_date DATE DEFAULT NULL"); } catch(Throwable $e) {}
try { $pdo->exec("ALTER TABLE class_groups ADD COLUMN end_date   DATE DEFAULT NULL"); } catch(Throwable $e) {}
// Sync bridge: optional link to old courses table so student/teacher_courses stay in sync
try { $pdo->exec("ALTER TABLE class_groups ADD COLUMN course_id INT UNSIGNED NULL DEFAULT NULL"); } catch(Throwable $e) {}

$pdo->exec("CREATE TABLE IF NOT EXISTS class_group_members (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  group_id    INT NOT NULL,
  user_id     INT NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member (group_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Fixed class type definitions ───────────────────────────────────────────
const CLASS_TYPES = [
  ['key'=>'beginners',         'label_fr'=>'Débutants',          'label_en'=>'Beginners',         'levels'=>3],
  ['key'=>'pre_intermediate',  'label_fr'=>'Pré-intermédiaire',  'label_en'=>'Pre-intermediate',  'levels'=>3],
  ['key'=>'intermediate',      'label_fr'=>'Intermédiaire',      'label_en'=>'Intermediate',            'levels'=>3],
  ['key'=>'upper_intermediate','label_fr'=>'Upper-intermédiaire','label_en'=>'Upper-intermediate',     'levels'=>3],
  ['key'=>'advanced',          'label_fr'=>'Avancé',             'label_en'=>'Advanced',            'levels'=>3],
  ['key'=>'baccalaureate',     'label_fr'=>'Baccalauréat',       'label_en'=>'Baccalaureate',       'levels'=>0],
  ['key'=>'business',          'label_fr'=>'Business',           'label_en'=>'Business',          'levels'=>0],
  ['key'=>'kids',              'label_fr'=>'Kids',               'label_en'=>'Kids',            'levels'=>0],
];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Always parse JSON body on POST so callers can mix GET ?action= with JSON payload
  $body = json_decode(file_get_contents('php://input'), true) ?? [];
  if ($action === '') $action = $body['action'] ?? '';
} else {
  $body = [];
}

// ── ACTIONS ────────────────────────────────────────────────────────────────

// list_types: all class types with group counts per level
if ($action === 'list_types') {
  $rows = $pdo->query(
    "SELECT type_key, level_number, COUNT(*) AS group_count FROM class_groups GROUP BY type_key, level_number"
  )->fetchAll(PDO::FETCH_ASSOC);

  $counts = [];
  foreach ($rows as $r) {
    $lvl = $r['level_number'] ?? 'null';
    $counts[$r['type_key']][$lvl] = (int)$r['group_count'];
  }

  $types = [];
  foreach (CLASS_TYPES as $t) {
    $entry = $t;
    if ($t['levels'] > 0) {
      $entry['level_groups'] = [];
      for ($l=1; $l<=$t['levels']; $l++) {
        $entry['level_groups'][] = ['level'=>$l, 'group_count'=>$counts[$t['key']][$l] ?? 0];
      }
    } else {
      $entry['group_count'] = $counts[$t['key']]['null'] ?? 0;
    }
    $types[] = $entry;
  }
  jsonOut(['ok'=>true, 'types'=>$types]);
}

// list_groups: groups for a type+level
if ($action === 'list_groups') {
  $typeKey = $_GET['type_key'] ?? '';
  $level   = isset($_GET['level']) && $_GET['level'] !== '' ? (int)$_GET['level'] : null;

  if (!$typeKey) err('type_key manquant');

  $sql = "SELECT g.id, g.group_letter, g.created_at, g.course_id,
            c.group_name_fr AS course_name_fr, c.group_name_ar AS course_name_ar,
            (SELECT COUNT(*) FROM class_group_members m WHERE m.group_id = g.id) AS member_count
          FROM class_groups g
          LEFT JOIN courses c ON c.id = g.course_id
          WHERE g.type_key = ?
            AND g.level_number " . ($level === null ? "IS NULL" : "= ?") . "
          ORDER BY g.group_letter";

  $params = [$typeKey];
  if ($level !== null) $params[] = $level;

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if ($groups) {
    $groupIds    = array_column($groups, 'id');
    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $mStmt = $pdo->prepare(
      "SELECT m.group_id, u.id, u.full_name AS name, u.role
       FROM class_group_members m
       JOIN users u ON u.id = m.user_id
       WHERE m.group_id IN ($placeholders)
       ORDER BY u.role DESC, u.full_name"
    );
    $mStmt->execute($groupIds);
    $byGroup = [];
    foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $mr) {
      $byGroup[$mr['group_id']][] = ['id'=>(int)$mr['id'], 'name'=>$mr['name'], 'role'=>$mr['role']];
    }
    foreach ($groups as &$g) {
      $g['members'] = $byGroup[$g['id']] ?? [];
    }
  }

  jsonOut(['ok'=>true, 'groups'=>$groups]);
}

// update_course: admin renames a course (group_name_fr / group_name_ar)
if ($action === 'update_course') {
  if ($role !== 'admin') err('Accès refusé', 403);
  $courseId    = (int)($body['course_id']    ?? 0);
  $nameFr      = trim($body['name_fr']       ?? '');
  $nameAr      = trim($body['name_ar']       ?? '');
  if (!$courseId || !$nameFr) err('course_id et name_fr requis');
  if (mb_strlen($nameFr) > 120 || mb_strlen($nameAr) > 120) err('Nom trop long (max 120 caractères)');
  $chk = $pdo->prepare("SELECT id FROM courses WHERE id = ?");
  $chk->execute([$courseId]);
  if (!$chk->fetch()) err('Cours introuvable', 404);
  $pdo->prepare("UPDATE courses SET group_name_fr = ?, group_name_ar = ? WHERE id = ?")
      ->execute([$nameFr, $nameAr ?: $nameFr, $courseId]);
  // Invalidate cached course list on next load
  jsonOut(['ok' => true]);
}

// create_course: admin adds a new course row
if ($action === 'create_course') {
  if ($role !== 'admin') err('Accès refusé', 403);
  $nameFr = trim($body['name_fr'] ?? '');
  $nameAr = trim($body['name_ar'] ?? '');
  if (!$nameFr) err('name_fr requis');
  if (mb_strlen($nameFr) > 120 || mb_strlen($nameAr) > 120) err('Nom trop long (max 120 caractères)');
  $stmt = $pdo->prepare("INSERT INTO courses (group_name_fr, group_name_ar, subject_fr, subject_ar, level)
                         VALUES (?, ?, ?, ?, 'A1')");
  $stmt->execute([$nameFr, $nameAr ?: $nameFr, $nameFr, $nameAr ?: $nameFr]);
  jsonOut(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
}

// list_courses: all courses for admin course-link dropdown
if ($action === 'list_courses') {
  if ($role !== 'admin') err('Accès refusé', 403);
  try {
    $stmt = $pdo->query("SELECT id, group_name_fr, group_name_ar, subject_fr, subject_ar, level
                         FROM courses ORDER BY group_name_fr");
    jsonOut(['ok'=>true, 'courses'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
  } catch (Throwable $e) {
    error_log('list_courses error: ' . $e->getMessage());
    err('Erreur serveur', 500);
  }
}

// link_course: admin links (or unlinks) a class_group to a course
// Also retroactively syncs all existing group members into student_courses / teacher_courses
if ($action === 'link_course') {
  if ($role !== 'admin') err('Accès refusé', 403);
  $groupId  = (int)($body['group_id']  ?? 0);
  $courseId = isset($body['course_id']) && $body['course_id'] !== '' && $body['course_id'] !== null
              ? (int)$body['course_id'] : null;
  if (!$groupId) err('group_id manquant');

  // Verify group exists
  $chk = $pdo->prepare("SELECT id FROM class_groups WHERE id = ?");
  $chk->execute([$groupId]);
  if (!$chk->fetch()) err('Groupe introuvable', 404);

  // If linking to a specific course, verify it exists
  if ($courseId) {
    $cchk = $pdo->prepare("SELECT id FROM courses WHERE id = ?");
    $cchk->execute([$courseId]);
    if (!$cchk->fetch()) err('Cours introuvable', 404);
  }

  // Save the link
  $pdo->prepare("UPDATE class_groups SET course_id = ? WHERE id = ?")->execute([$courseId, $groupId]);

  // Retroactively sync all existing members into student_courses / teacher_courses
  if ($courseId) {
    $members = $pdo->prepare(
      "SELECT cgm.user_id, u.role FROM class_group_members cgm
       JOIN users u ON u.id = cgm.user_id WHERE cgm.group_id = ?"
    );
    $members->execute([$groupId]);
    foreach ($members->fetchAll(PDO::FETCH_ASSOC) as $m) {
      if ($m['role'] === 'student') {
        try { $pdo->prepare("INSERT IGNORE INTO student_courses (student_id, course_id) VALUES (?, ?)")->execute([$m['user_id'], $courseId]); } catch(Throwable $e) {}
      } elseif ($m['role'] === 'teacher') {
        try { $pdo->prepare("INSERT IGNORE INTO teacher_courses (teacher_id, course_id) VALUES (?, ?)")->execute([$m['user_id'], $courseId]); } catch(Throwable $e) {}
      }
    }
  }

  jsonOut(['ok'=>true]);
}

// create_group
if ($action === 'create_group') {
  if ($role !== 'admin') err('Accès refusé', 403);
  $data   = $body;
  $typeKey = trim($data['type_key'] ?? '');
  $level   = isset($data['level']) && $data['level'] !== '' && $data['level'] !== null ? (int)$data['level'] : null;
  $letter  = strtoupper(trim($data['group_letter'] ?? ''));
  if (!$typeKey || !$letter) err('Données manquantes');

  // Validate type_key
  $validKeys = array_column(CLASS_TYPES, 'key');
  if (!in_array($typeKey, $validKeys, true)) err('Type invalide');

  try {
    $stmt = $pdo->prepare("INSERT INTO class_groups (type_key, level_number, group_letter) VALUES (?, ?, ?)");
    $stmt->execute([$typeKey, $level, $letter]);
    $id = (int)$pdo->lastInsertId();
    jsonOut(['ok'=>true, 'id'=>$id, 'group_letter'=>$letter]);
  } catch (PDOException $e) {
    if ($e->getCode() === '23000') err('Ce groupe existe déjà');
    err('Erreur base de données');
  }
}

// delete_group
if ($action === 'delete_group') {
  if ($role !== 'admin') err('Accès refusé', 403);
  $data    = $body;
  $groupId = (int)($data['group_id'] ?? 0);
  if (!$groupId) err('group_id manquant');
  $pdo->prepare("DELETE FROM class_groups WHERE id=?")->execute([$groupId]);
  jsonOut(['ok'=>true]);
}

// list_members: students and teachers in a group (admin only)
if ($action === 'list_members') {
  if ($role !== 'admin') err('Accès refusé', 403);
  $groupId = (int)($_GET['group_id'] ?? 0);
  if (!$groupId) err('group_id manquant');

  $stmt = $pdo->prepare(
    "SELECT u.id, u.full_name AS name, u.username, u.role, m.assigned_at
     FROM class_group_members m
     JOIN users u ON u.id = m.user_id
     WHERE m.group_id = ?
     ORDER BY u.role DESC, u.full_name"
  );
  $stmt->execute([$groupId]);
  jsonOut(['ok'=>true, 'members'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// add_member
if ($action === 'add_member') {
  if ($role !== 'admin') err('Accès refusé', 403);
  $data    = $body;
  $groupId = (int)($data['group_id'] ?? 0);
  $uid     = (int)($data['user_id']  ?? 0);
  if (!$groupId || !$uid) err('Données manquantes');

  try {
    $pdo->prepare("INSERT INTO class_group_members (group_id, user_id) VALUES (?, ?)")->execute([$groupId, $uid]);

    // Sync bridge: if this group is linked to a course, keep student_courses / teacher_courses in sync
    $grp = $pdo->prepare("SELECT course_id FROM class_groups WHERE id = ?");
    $grp->execute([$groupId]);
    $courseId = (int)($grp->fetchColumn() ?: 0);
    if ($courseId) {
      $userRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
      $userRole->execute([$uid]);
      $memberRole = $userRole->fetchColumn();
      if ($memberRole === 'student') {
        try {
          $pdo->prepare("INSERT IGNORE INTO student_courses (student_id, course_id) VALUES (?, ?)")->execute([$uid, $courseId]);
        } catch(Throwable $e) {}
      } elseif ($memberRole === 'teacher') {
        try {
          $pdo->prepare("INSERT IGNORE INTO teacher_courses (teacher_id, course_id) VALUES (?, ?)")->execute([$uid, $courseId]);
        } catch(Throwable $e) {}
      }
    }

    jsonOut(['ok'=>true]);
  } catch (PDOException $e) {
    if ($e->getCode() === '23000') err('Déjà membre de ce groupe');
    err('Erreur base de données');
  }
}

// remove_member
if ($action === 'remove_member') {
  if ($role !== 'admin') err('Accès refusé', 403);
  $data    = $body;
  $groupId = (int)($data['group_id'] ?? 0);
  $uid     = (int)($data['user_id']  ?? 0);
  if (!$groupId || !$uid) err('Données manquantes');

  // Sync bridge: remove from student_courses / teacher_courses if group has a linked course
  $grp = $pdo->prepare("SELECT course_id FROM class_groups WHERE id = ?");
  $grp->execute([$groupId]);
  $courseId = (int)($grp->fetchColumn() ?: 0);
  if ($courseId) {
    $userRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $userRole->execute([$uid]);
    $memberRole = $userRole->fetchColumn();
    if ($memberRole === 'student') {
      try { $pdo->prepare("DELETE FROM student_courses WHERE student_id=? AND course_id=?")->execute([$uid, $courseId]); } catch(Throwable $e) {}
    } elseif ($memberRole === 'teacher') {
      try { $pdo->prepare("DELETE FROM teacher_courses WHERE teacher_id=? AND course_id=?")->execute([$uid, $courseId]); } catch(Throwable $e) {}
    }
  }

  $pdo->prepare("DELETE FROM class_group_members WHERE group_id=? AND user_id=?")->execute([$groupId, $uid]);
  jsonOut(['ok'=>true]);
}

// list_all_users: teachers + unassigned students only (students in any group are excluded)
if ($action === 'list_all_users') {
  if ($role !== 'admin') err('Accès refusé', 403);
  $stmt = $pdo->query(
    "SELECT u.id, u.full_name AS name, u.username, u.role
     FROM users u
     WHERE u.role IN ('student','teacher')
       AND (u.role = 'teacher'
            OR u.id NOT IN (
              SELECT m.user_id FROM class_group_members m
              JOIN users u2 ON u2.id = m.user_id WHERE u2.role = 'student'
            ))
     ORDER BY u.role DESC, u.full_name"
  );
  jsonOut(['ok'=>true, 'users'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// list_assignments: all group assignments, grouped by user (for "Assigning Classes" admin page)
if ($action === 'list_assignments') {
  if ($role !== 'admin') err('Accès refusé', 403);

  $typeLabels = [];
  foreach (CLASS_TYPES as $t) { $typeLabels[$t['key']] = ['fr'=>$t['label_fr'], 'en'=>$t['label_en']]; }

  // Assigned students and teachers (via class_group_members)
  $stmt = $pdo->query(
    "SELECT u.id AS user_id, u.full_name AS name, u.username, u.role,
            g.id AS group_id, g.type_key, g.level_number, g.group_letter
     FROM class_group_members m
     JOIN users u        ON u.id = m.user_id
     JOIN class_groups g ON g.id = m.group_id
     ORDER BY u.role DESC, u.full_name"
  );
  $byUser = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $uid = $r['user_id'];
    if (!isset($byUser[$uid])) {
      $byUser[$uid] = ['id'=>$uid,'name'=>$r['name'],'username'=>$r['username'],'role'=>$r['role'],'groups'=>[]];
    }
    $lvl = $r['level_number'];
    $byUser[$uid]['groups'][] = [
      'group_id'    => (int)$r['group_id'],
      'type_key'    => $r['type_key'],
      'level_number'=> $lvl ? (int)$lvl : null,
      'group_letter'=> $r['group_letter'],
      'label_fr'    => $typeLabels[$r['type_key']]['fr'] . ($lvl ? ' ' . $lvl : '') . ' – Groupe ' . $r['group_letter'],
      'label_en'    => $typeLabels[$r['type_key']]['en'] . ($lvl ? ' ' . $lvl : '') . ' – Group ' . $r['group_letter'],
    ];
  }

  // Teachers with no group assignments (add them with empty groups array)
  $unassignedTeachers = $pdo->query(
    "SELECT u.id AS user_id, u.full_name AS name, u.username
     FROM users u
     WHERE u.role = 'teacher'
       AND u.id NOT IN (SELECT user_id FROM class_group_members)
     ORDER BY u.full_name"
  )->fetchAll(PDO::FETCH_ASSOC);
  foreach ($unassignedTeachers as $t) {
    $byUser[$t['user_id']] = ['id'=>$t['user_id'],'name'=>$t['name'],'username'=>$t['username'],'role'=>'teacher','groups'=>[]];
  }

  $students = array_values(array_filter($byUser, fn($u) => $u['role']==='student'));
  $teachers = array_values(array_filter($byUser, fn($u) => $u['role']==='teacher'));
  usort($teachers, fn($a,$b) => strcmp($a['name'] ?? $a['username'], $b['name'] ?? $b['username']));

  $unassigned_students = $pdo->query(
    "SELECT u.id AS user_id, u.full_name AS name, u.username
     FROM users u
     WHERE u.role = 'student'
       AND u.id NOT IN (SELECT user_id FROM class_group_members)
     ORDER BY u.full_name"
  )->fetchAll(PDO::FETCH_ASSOC);

  jsonOut(['ok'=>true, 'students'=>$students, 'teachers'=>$teachers, 'unassigned_students'=>$unassigned_students]);
}

// my_group: student/teacher sees their own group(s)
if ($action === 'my_group') {
  $stmt = $pdo->prepare(
    "SELECT g.id AS group_id, g.type_key, g.level_number, g.group_letter,
            g.start_date, g.end_date
     FROM class_group_members m
     JOIN class_groups g ON g.id = m.group_id
     WHERE m.user_id = ?"
  );
  $stmt->execute([$userId]);
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $typeLabels = [];
  foreach (CLASS_TYPES as $t) { $typeLabels[$t['key']] = ['fr'=>$t['label_fr'], 'en'=>$t['label_en']]; }

  $result = [];
  foreach ($groups as $g) {
    $lvl = $g['level_number'];
    $result[] = [
      'group_id'    => (int)$g['group_id'],
      'type_key'    => $g['type_key'],
      'level_number'=> $lvl ? (int)$lvl : null,
      'group_letter'=> $g['group_letter'],
      'start_date'  => $g['start_date'],
      'end_date'    => $g['end_date'],
      'label_fr'    => $typeLabels[$g['type_key']]['fr'] . ($lvl ? ' ' . $lvl : '') . ' – Groupe ' . $g['group_letter'],
      'label_en'    => $typeLabels[$g['type_key']]['en'] . ($lvl ? ' ' . $lvl : '') . ' – Group ' . $g['group_letter'],
    ];
  }
  jsonOut(['ok'=>true, 'groups'=>$result]);
}

// group_classmates: all members of a specific group (for student dashboard — caller must belong to the group)
if ($action === 'group_classmates') {
  $groupId = (int)($_GET['group_id'] ?? 0);
  if (!$groupId) err('group_id manquant');

  // Students and teachers may only view groups they belong to
  if ($role !== 'admin') {
    $membership = $pdo->prepare("SELECT 1 FROM class_group_members WHERE group_id = ? AND user_id = ?");
    $membership->execute([$groupId, $userId]);
    if (!$membership->fetch()) err('Accès refusé', 403);
  }

  $stmt = $pdo->prepare(
    "SELECT u.id, u.full_name AS name, u.username, u.role
     FROM class_group_members m
     JOIN users u ON u.id = m.user_id
     WHERE m.group_id = ?
     ORDER BY u.role DESC, u.full_name"
  );
  $stmt->execute([$groupId]);
  jsonOut(['ok'=>true, 'members'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// teacher_groups: groups where a teacher is assigned (for teacher dashboard)
if ($action === 'teacher_groups') {
  $tid = $role === 'admin' ? (int)($_GET['teacher_id'] ?? $userId) : $userId;
  if ($role === 'student') err('Accès refusé', 403);

  $stmt = $pdo->prepare(
    "SELECT g.id AS group_id, g.type_key, g.level_number, g.group_letter,
            g.schedule_json, g.zoom_url, g.course_id, g.start_date, g.end_date,
            (SELECT COUNT(*) FROM class_group_members m2
             JOIN users u2 ON u2.id=m2.user_id
             WHERE m2.group_id=g.id AND u2.role='student') AS student_count
     FROM class_group_members m
     JOIN class_groups g ON g.id = m.group_id
     WHERE m.user_id = ?
     ORDER BY g.type_key, g.level_number, g.group_letter"
  );
  $stmt->execute([$tid]);
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $typeLabels = [];
  foreach (CLASS_TYPES as $t) { $typeLabels[$t['key']] = ['fr'=>$t['label_fr'], 'en'=>$t['label_en']]; }

  foreach ($groups as &$g) {
    $lvl = $g['level_number'];
    $g['label_fr'] = $typeLabels[$g['type_key']]['fr'] . ($lvl ? ' ' . $lvl : '') . ' – Groupe ' . $g['group_letter'];
    $g['label_en'] = $typeLabels[$g['type_key']]['en'] . ($lvl ? ' ' . $lvl : '') . ' – Group ' . $g['group_letter'];
    $g['group_id'] = (int)$g['group_id'];
    $g['level_number'] = $lvl ? (int)$lvl : null;
    $g['student_count'] = (int)$g['student_count'];
  }
  jsonOut(['ok'=>true, 'groups'=>$groups]);
}

// group_students: all students in a specific group (for teacher attendance — teacher must be assigned to the group)
if ($action === 'group_students') {
  $groupId = (int)($_GET['group_id'] ?? 0);
  if (!$groupId) err('group_id manquant');
  if ($role === 'student') err('Accès refusé', 403);

  // Teachers may only query groups they are assigned to
  if ($role === 'teacher') {
    $membership = $pdo->prepare("SELECT 1 FROM class_group_members WHERE group_id = ? AND user_id = ?");
    $membership->execute([$groupId, $userId]);
    if (!$membership->fetch()) err('Accès refusé', 403);
  }

  $stmt = $pdo->prepare(
    "SELECT u.id, u.full_name AS name, u.username
     FROM class_group_members m
     JOIN users u ON u.id = m.user_id
     WHERE m.group_id = ? AND u.role = 'student'
     ORDER BY u.full_name"
  );
  $stmt->execute([$groupId]);
  jsonOut(['ok'=>true, 'students'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// teacher_all_students: distinct students across all groups assigned to the teacher
if ($action === 'teacher_all_students') {
  if ($role === 'student') err('Accès refusé', 403);
  $tid = $role === 'admin' ? (int)($_GET['teacher_id'] ?? $userId) : $userId;

  $stmt = $pdo->prepare(
    "SELECT DISTINCT u.id, u.full_name AS name, u.username
     FROM class_group_members tm
     JOIN class_groups g      ON g.id  = tm.group_id
     JOIN class_group_members sm ON sm.group_id = g.id
     JOIN users u             ON u.id  = sm.user_id
     WHERE tm.user_id = ? AND u.role = 'student'
     ORDER BY u.full_name"
  );
  $stmt->execute([$tid]);
  jsonOut(['ok'=>true, 'students'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// list_all_groups: every group with schedule, teacher, student count (admin only)
if ($action === 'list_all_groups') {
  if ($role !== 'admin') err('Accès refusé', 403);

  $stmt = $pdo->query(
    "SELECT g.id AS group_id, g.type_key, g.level_number, g.group_letter,
            g.schedule_json, g.zoom_url, g.start_date, g.end_date,
            (SELECT u.full_name
               FROM class_group_members m2
               JOIN users u ON u.id = m2.user_id
              WHERE m2.group_id = g.id AND u.role = 'teacher'
              LIMIT 1) AS teacher_name,
            (SELECT COUNT(*)
               FROM class_group_members m3
               JOIN users u3 ON u3.id = m3.user_id
              WHERE m3.group_id = g.id AND u3.role = 'student') AS student_count
     FROM class_groups g
     ORDER BY
       CASE g.type_key
         WHEN 'beginners'          THEN 1
         WHEN 'pre_intermediate'   THEN 2
         WHEN 'intermediate'       THEN 3
         WHEN 'upper_intermediate' THEN 4
         WHEN 'advanced'           THEN 5
         WHEN 'baccalaureate'      THEN 6
         WHEN 'business'           THEN 7
         WHEN 'kids'               THEN 8
         ELSE 9
       END,
       g.level_number, g.group_letter"
  );
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $typeLabels = [];
  foreach (CLASS_TYPES as $t) { $typeLabels[$t['key']] = ['fr'=>$t['label_fr'], 'en'=>$t['label_en']]; }

  foreach ($groups as &$g) {
    $lvl = $g['level_number'];
    $g['label_fr']     = $typeLabels[$g['type_key']]['fr'] . ($lvl ? ' ' . $lvl : '') . ' – Groupe ' . $g['group_letter'];
    $g['label_en']     = $typeLabels[$g['type_key']]['en'] . ($lvl ? ' ' . $lvl : '') . ' – Group ' . $g['group_letter'];
    $g['group_id']     = (int)$g['group_id'];
    $g['level_number'] = $lvl ? (int)$lvl : null;
    $g['student_count']= (int)$g['student_count'];
  }
  jsonOut(['ok'=>true, 'groups'=>$groups]);
}

// update_schedule: admin sets schedule_json for a class_group
if ($action === 'update_schedule') {
  if ($role !== 'admin') err('Accès refusé', 403);

  $groupId = filter_var($body['group_id'] ?? null, FILTER_VALIDATE_INT);
  if (!$groupId) err('group_id invalide');

  // Verify group exists
  $check = $pdo->prepare("SELECT id FROM class_groups WHERE id = ?");
  $check->execute([$groupId]);
  if (!$check->fetch()) err('Groupe introuvable', 404);

  $slots = $body['schedule'] ?? [];
  $VALID_DAYS_FR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
  $clean = [];
  if (is_array($slots)) {
    foreach ($slots as $s) {
      $day     = trim($s['day_fr']  ?? '');
      $time    = trim($s['time']    ?? '');
      $timeEnd = trim($s['time_end'] ?? '');
      if (!in_array($day, $VALID_DAYS_FR, true)) continue;
      // Normalize start time: accept "H:MM" → "HH:MM"
      if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
        $time = str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
      }
      if (!preg_match('/^\d{2}:\d{2}$/', $time)) continue;
      // Normalize end time (optional)
      if ($timeEnd !== '') {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $timeEnd, $m2)) {
          $timeEnd = str_pad($m2[1], 2, '0', STR_PAD_LEFT) . ':' . $m2[2];
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $timeEnd)) $timeEnd = '';
      }
      $clean[] = ['day_fr'=>$day, 'time'=>$time, 'time_end'=>$timeEnd];
    }
  }

  $startDate = trim($body['start_date'] ?? '');
  $endDate   = trim($body['end_date']   ?? '');
  if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = '';
  if ($endDate   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))   $endDate   = '';

  $json = count($clean) > 0 ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
  $pdo->prepare("UPDATE class_groups SET schedule_json = ?, start_date = ?, end_date = ? WHERE id = ?")
      ->execute([$json, $startDate ?: null, $endDate ?: null, $groupId]);
  jsonOut(['ok'=>true, 'saved'=>count($clean)]);
}

// set_group_zoom_url: teacher (or admin) sets zoom_url for a class_group
if ($action === 'set_group_zoom_url') {
  $groupId = filter_var($body['group_id'] ?? null, FILTER_VALIDATE_INT);
  $zoomUrl = trim($body['zoom_url'] ?? '');
  if (!$groupId) err('group_id invalide');

  // Teachers may only update groups they belong to
  if ($role === 'teacher') {
    $owns = $pdo->prepare("SELECT 1 FROM class_group_members WHERE group_id = ? AND user_id = ?");
    $owns->execute([$groupId, $userId]);
    if (!$owns->fetch()) err('Accès refusé', 403);
  } elseif ($role !== 'admin') {
    err('Accès refusé', 403);
  }

  // Basic URL validation
  if ($zoomUrl !== '' && !filter_var($zoomUrl, FILTER_VALIDATE_URL)) err('URL invalide');

  $pdo->prepare("UPDATE class_groups SET zoom_url = ? WHERE id = ?")->execute([$zoomUrl ?: null, $groupId]);
  jsonOut(['ok'=>true]);
}

err('Action inconnue: ' . htmlspecialchars($action), 400);
