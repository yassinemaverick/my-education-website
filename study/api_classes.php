<?php
// Suppress any stray PHP notices/warnings so they don't corrupt JSON output
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
session_start();
require_once __DIR__ . '/db.php';

function jsonOut(array $data): void {
  ob_end_clean(); // discard any accidental output before JSON
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function err(string $msg, int $code=400): void { http_response_code($code); jsonOut(['ok'=>false,'error'=>$msg]); }

// Auth
$role   = $_SESSION['role']  ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) err('Non authentifié', 401);

// CSRF for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) err('Token CSRF invalide', 403);
}

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

$pdo->exec("CREATE TABLE IF NOT EXISTS class_group_members (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  group_id    INT NOT NULL,
  user_id     INT NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member (group_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Fixed class type definitions ───────────────────────────────────────────
const CLASS_TYPES = [
  ['key'=>'beginners',         'label_fr'=>'Débutants',          'label_ar'=>'مبتدئون',         'levels'=>3],
  ['key'=>'pre_intermediate',  'label_fr'=>'Pré-intermédiaire',  'label_ar'=>'ما قبل المتوسط',  'levels'=>3],
  ['key'=>'intermediate',      'label_fr'=>'Intermédiaire',      'label_ar'=>'متوسط',            'levels'=>3],
  ['key'=>'upper_intermediate','label_fr'=>'Upper-intermédiaire','label_ar'=>'فوق المتوسط',     'levels'=>3],
  ['key'=>'advanced',          'label_fr'=>'Avancé',             'label_ar'=>'متقدم',            'levels'=>3],
  ['key'=>'baccalaureate',     'label_fr'=>'Baccalauréat',       'label_ar'=>'البكالوريا',       'levels'=>0],
  ['key'=>'business',          'label_fr'=>'Business',           'label_ar'=>'الأعمال',          'levels'=>0],
  ['key'=>'kids',              'label_fr'=>'Kids',               'label_ar'=>'أطفال',            'levels'=>0],
];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if ($action === '' && $_SERVER['REQUEST_METHOD']==='POST') {
  $body = json_decode(file_get_contents('php://input'), true) ?? [];
  $action = $body['action'] ?? '';
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

  $sql = "SELECT g.id, g.group_letter, g.created_at,
            (SELECT COUNT(*) FROM class_group_members m WHERE m.group_id = g.id) AS member_count
          FROM class_groups g
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

// create_group
if ($action === 'create_group') {
  if ($role !== 'admin') err('Accès refusé', 403);
  $data   = json_decode(file_get_contents('php://input'), true) ?? [];
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
  $data    = json_decode(file_get_contents('php://input'), true) ?? [];
  $groupId = (int)($data['group_id'] ?? 0);
  if (!$groupId) err('group_id manquant');
  $pdo->prepare("DELETE FROM class_groups WHERE id=?")->execute([$groupId]);
  jsonOut(['ok'=>true]);
}

// list_members: students and teachers in a group
if ($action === 'list_members') {
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
  $data    = json_decode(file_get_contents('php://input'), true) ?? [];
  $groupId = (int)($data['group_id'] ?? 0);
  $uid     = (int)($data['user_id']  ?? 0);
  if (!$groupId || !$uid) err('Données manquantes');

  try {
    $pdo->prepare("INSERT INTO class_group_members (group_id, user_id) VALUES (?, ?)")->execute([$groupId, $uid]);
    jsonOut(['ok'=>true]);
  } catch (PDOException $e) {
    if ($e->getCode() === '23000') err('Déjà membre de ce groupe');
    err('Erreur base de données');
  }
}

// remove_member
if ($action === 'remove_member') {
  if ($role !== 'admin') err('Accès refusé', 403);
  $data    = json_decode(file_get_contents('php://input'), true) ?? [];
  $groupId = (int)($data['group_id'] ?? 0);
  $uid     = (int)($data['user_id']  ?? 0);
  if (!$groupId || !$uid) err('Données manquantes');
  $pdo->prepare("DELETE FROM class_group_members WHERE group_id=? AND user_id=?")->execute([$groupId, $uid]);
  jsonOut(['ok'=>true]);
}

// list_all_users: all students and teachers for the add-member dropdown
if ($action === 'list_all_users') {
  if ($role !== 'admin') err('Accès refusé', 403);
  $stmt = $pdo->query(
    "SELECT id, full_name AS name, username, role FROM users WHERE role IN ('student','teacher') ORDER BY role DESC, full_name"
  );
  jsonOut(['ok'=>true, 'users'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// list_assignments: all group assignments, grouped by user (for "Assigning Classes" admin page)
if ($action === 'list_assignments') {
  if ($role !== 'admin') err('Accès refusé', 403);

  $stmt = $pdo->query(
    "SELECT u.id AS user_id, u.full_name AS name, u.username, u.role,
            g.id AS group_id, g.type_key, g.level_number, g.group_letter
     FROM class_group_members m
     JOIN users u        ON u.id = m.user_id
     JOIN class_groups g ON g.id = m.group_id
     ORDER BY u.role DESC, u.full_name"
  );

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Build type label map
  $typeLabels = [];
  foreach (CLASS_TYPES as $t) { $typeLabels[$t['key']] = ['fr'=>$t['label_fr'], 'ar'=>$t['label_ar']]; }

  $byUser = [];
  foreach ($rows as $r) {
    $uid = $r['user_id'];
    if (!isset($byUser[$uid])) {
      $byUser[$uid] = ['id'=>$uid,'name'=>$r['name'],'username'=>$r['username'],'role'=>$r['role'],'groups'=>[]];
    }
    $lvl = $r['level_number'];
    $groupLabel = $typeLabels[$r['type_key']]['fr'] . ($lvl ? ' ' . $lvl : '') . ' – Groupe ' . $r['group_letter'];
    $byUser[$uid]['groups'][] = [
      'group_id'    => (int)$r['group_id'],
      'type_key'    => $r['type_key'],
      'level_number'=> $lvl ? (int)$lvl : null,
      'group_letter'=> $r['group_letter'],
      'label_fr'    => $typeLabels[$r['type_key']]['fr'] . ($lvl ? ' ' . $lvl : '') . ' – Groupe ' . $r['group_letter'],
      'label_ar'    => $typeLabels[$r['type_key']]['ar'] . ($lvl ? ' ' . $lvl : '') . ' – مجموعة ' . $r['group_letter'],
    ];
  }

  $students = array_values(array_filter($byUser, fn($u) => $u['role']==='student'));
  $teachers = array_values(array_filter($byUser, fn($u) => $u['role']==='teacher'));
  jsonOut(['ok'=>true, 'students'=>$students, 'teachers'=>$teachers]);
}

// my_group: student/teacher sees their own group(s)
if ($action === 'my_group') {
  $stmt = $pdo->prepare(
    "SELECT g.id AS group_id, g.type_key, g.level_number, g.group_letter
     FROM class_group_members m
     JOIN class_groups g ON g.id = m.group_id
     WHERE m.user_id = ?"
  );
  $stmt->execute([$userId]);
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $typeLabels = [];
  foreach (CLASS_TYPES as $t) { $typeLabels[$t['key']] = ['fr'=>$t['label_fr'], 'ar'=>$t['label_ar']]; }

  $result = [];
  foreach ($groups as $g) {
    $lvl = $g['level_number'];
    $result[] = [
      'group_id'    => (int)$g['group_id'],
      'type_key'    => $g['type_key'],
      'level_number'=> $lvl ? (int)$lvl : null,
      'group_letter'=> $g['group_letter'],
      'label_fr'    => $typeLabels[$g['type_key']]['fr'] . ($lvl ? ' ' . $lvl : '') . ' – Groupe ' . $g['group_letter'],
      'label_ar'    => $typeLabels[$g['type_key']]['ar'] . ($lvl ? ' ' . $lvl : '') . ' – مجموعة ' . $g['group_letter'],
    ];
  }
  jsonOut(['ok'=>true, 'groups'=>$result]);
}

// group_classmates: all members of a specific group (for student dashboard)
if ($action === 'group_classmates') {
  $groupId = (int)($_GET['group_id'] ?? 0);
  if (!$groupId) err('group_id manquant');

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
  foreach (CLASS_TYPES as $t) { $typeLabels[$t['key']] = ['fr'=>$t['label_fr'], 'ar'=>$t['label_ar']]; }

  foreach ($groups as &$g) {
    $lvl = $g['level_number'];
    $g['label_fr'] = $typeLabels[$g['type_key']]['fr'] . ($lvl ? ' ' . $lvl : '') . ' – Groupe ' . $g['group_letter'];
    $g['label_ar'] = $typeLabels[$g['type_key']]['ar'] . ($lvl ? ' ' . $lvl : '') . ' – مجموعة ' . $g['group_letter'];
    $g['group_id'] = (int)$g['group_id'];
    $g['level_number'] = $lvl ? (int)$lvl : null;
    $g['student_count'] = (int)$g['student_count'];
  }
  jsonOut(['ok'=>true, 'groups'=>$groups]);
}

// group_students: all students in a specific group (for teacher attendance)
if ($action === 'group_students') {
  $groupId = (int)($_GET['group_id'] ?? 0);
  if (!$groupId) err('group_id manquant');
  if ($role === 'student') err('Accès refusé', 403);

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

err('Action inconnue: ' . htmlspecialchars($action), 400);
