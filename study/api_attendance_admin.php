<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit;
}

require_once __DIR__ . '/db.php';
ensureAttendanceTable();
ensureSessionDateOverridesTable();

$action = $_GET['action'] ?? '';

// ── Compute how many sessions should have happened by today ──────────────────
function computeSessionsDue(?string $startDate, ?string $scheduleJson): int {
    if (!$startDate || !$scheduleJson) return 0;
    $slots = json_decode($scheduleJson, true);
    if (!is_array($slots) || empty($slots)) return 0;
    $dayMap = ['dimanche'=>0,'lundi'=>1,'mardi'=>2,'mercredi'=>3,'jeudi'=>4,'vendredi'=>5,'samedi'=>6];
    $slotDays = array_unique(array_values(array_filter(
        array_map(fn($s) => $dayMap[strtolower($s['day_fr'] ?? '')] ?? null, $slots),
        fn($d) => $d !== null
    )));
    if (empty($slotDays)) return 0;
    try {
        $cursor = new DateTime($startDate . ' 00:00:00');
        $today  = new DateTime('today 23:59:59');
    } catch (Exception $e) { return 0; }
    $count = 0; $limit = 400;
    while ($cursor <= $today && $count < 20 && $limit-- > 0) {
        if (in_array((int)$cursor->format('w'), $slotDays)) $count++;
        $cursor->modify('+1 day');
    }
    return $count;
}

// ── Type label map (mirrors teacher/student dashboards) ──────────────────────
$typeLabels = [
    'beginners'          => ['fr'=>'Débutants',          'en'=>'Beginners'],
    'pre_intermediate'   => ['fr'=>'Pré-intermédiaire',  'en'=>'Pre-Intermediate'],
    'intermediate'       => ['fr'=>'Intermédiaire',       'en'=>'Intermediate'],
    'upper_intermediate' => ['fr'=>'Inter. supérieur',    'en'=>'Upper Intermediate'],
    'advanced'           => ['fr'=>'Avancé',              'en'=>'Advanced'],
    'baccalaureate'      => ['fr'=>'Baccalauréat',        'en'=>'Baccalaureate'],
    'business'           => ['fr'=>'Business English',    'en'=>'Business English'],
    'kids'               => ['fr'=>'Enfants',             'en'=>'Kids'],
];

function groupLabel(array $g, array $typeLabels): string {
    $tl  = $typeLabels[$g['type_key']] ?? ['fr'=>$g['type_key'],'en'=>$g['type_key']];
    $lbl = $tl['fr'];
    if ($g['level_number']) $lbl .= ' ' . $g['level_number'];
    $lbl .= ' – Groupe ' . $g['group_letter'];
    return $lbl;
}

// ── ACTION: overview ─────────────────────────────────────────────────────────
if ($action === 'overview') {
    $pdo = db();

    // All groups with teacher and student count
    $groups = $pdo->query("
        SELECT g.id as group_id, g.type_key, g.level_number, g.group_letter,
               g.start_date, g.schedule_json,
               ut.id   as teacher_id,
               COALESCE(ut.full_name, ut.username) as teacher_name,
               COUNT(DISTINCT ms.user_id) as student_count
        FROM class_groups g
        LEFT JOIN class_group_members mt ON mt.group_id = g.id
        LEFT JOIN users ut ON ut.id = mt.user_id AND ut.role = 'teacher'
        LEFT JOIN class_group_members ms ON ms.group_id = g.id
        LEFT JOIN users us ON us.id = ms.user_id AND us.role = 'student'
        GROUP BY g.id, ut.id
        ORDER BY g.type_key, g.level_number, g.group_letter
    ")->fetchAll();

    // Sessions conducted per group: distinct session_nums where both teacher and student are in the group
    $sessRows = $pdo->query("
        SELECT mt.group_id, COUNT(DISTINCT a.session_num) as sessions_conducted
        FROM attendance a
        JOIN class_group_members mt ON mt.user_id = a.teacher_id
        JOIN class_group_members ms ON ms.user_id = a.student_id AND ms.group_id = mt.group_id
        GROUP BY mt.group_id
    ")->fetchAll();
    $sessByGroup = [];
    foreach ($sessRows as $r) $sessByGroup[(int)$r['group_id']] = (int)$r['sessions_conducted'];

    // Per-student attendance per group (present count out of sessions conducted)
    $attRows = $pdo->query("
        SELECT ms.group_id,
               ms.user_id as student_id,
               COUNT(DISTINCT CASE WHEN a.present = 1 THEN a.session_num END) as present_count,
               COUNT(DISTINCT a.session_num) as marked_sessions
        FROM class_group_members ms
        JOIN users us ON us.id = ms.user_id AND us.role = 'student'
        LEFT JOIN class_group_members mt ON mt.group_id = ms.group_id
        LEFT JOIN users ut ON ut.id = mt.user_id AND ut.role = 'teacher'
        LEFT JOIN attendance a ON a.student_id = ms.user_id AND a.teacher_id = ut.id
        GROUP BY ms.group_id, ms.user_id
    ")->fetchAll();

    // Index attendance by group_id -> student_id
    $attByGroup = [];
    foreach ($attRows as $r) {
        $attByGroup[(int)$r['group_id']][(int)$r['student_id']] = [
            'present'  => (int)$r['present_count'],
            'sessions' => (int)$r['marked_sessions'],
        ];
    }

    $result = [];
    $totalPresent = 0; $totalCells = 0; $totalSessDone = 0; $totalSessDue = 0;
    $atRiskTotal = 0; $teacherIds = []; $teachersOnTrack = 0;

    foreach ($groups as $g) {
        $gid       = (int)$g['group_id'];
        $sessDone  = $sessByGroup[$gid] ?? 0;
        $sessDue   = computeSessionsDue($g['start_date'], $g['schedule_json']);
        $students  = $attByGroup[$gid] ?? [];
        $studentCount = (int)$g['student_count'];

        // Avg attendance and at-risk
        $pctSum = 0; $pctCount = 0; $atRisk = 0;
        foreach ($students as $sid => $d) {
            if ($sessDone > 0) {
                $pct = round(($d['present'] / $sessDone) * 100);
                $pctSum += $pct; $pctCount++;
                if ($pct < 70) $atRisk++;
            }
        }
        $avgPct = $pctCount > 0 ? round($pctSum / $pctCount) : null;

        $totalSessDone += $sessDone;
        $totalSessDue  += $sessDue;
        $totalPresent  += array_sum(array_column(array_values($students), 'present'));
        $totalCells    += $sessDone * $studentCount;
        $atRiskTotal   += $atRisk;

        $tid = $g['teacher_id'] ? (int)$g['teacher_id'] : null;
        if ($tid && !isset($teacherIds[$tid])) {
            $teacherIds[$tid] = ['on_track' => ($sessDone >= $sessDue)];
        } elseif ($tid) {
            $teacherIds[$tid]['on_track'] = $teacherIds[$tid]['on_track'] && ($sessDone >= $sessDue);
        }

        $result[] = [
            'group_id'             => $gid,
            'label'                => groupLabel($g, $typeLabels),
            'teacher_id'           => $tid,
            'teacher_name'         => $g['teacher_name'],
            'student_count'        => $studentCount,
            'sessions_conducted'   => $sessDone,
            'sessions_due'         => $sessDue,
            'avg_attendance_pct'   => $avgPct,
            'at_risk_count'        => $atRisk,
        ];
    }

    foreach ($teacherIds as $t) { if ($t['on_track']) $teachersOnTrack++; }
    $overallAvg = $totalCells > 0 ? round(($totalPresent / $totalCells) * 100) : null;

    echo json_encode([
        'ok'     => true,
        'groups' => $result,
        'summary' => [
            'avg_attendance'           => $overallAvg,
            'total_sessions_conducted' => $totalSessDone,
            'total_sessions_due'       => $totalSessDue,
            'at_risk_students'         => $atRiskTotal,
            'teachers_on_track'        => $teachersOnTrack,
            'total_teachers'           => count($teacherIds),
        ],
    ]);
    exit;
}

// ── ACTION: group_detail ─────────────────────────────────────────────────────
if ($action === 'group_detail') {
    $groupId = (int)($_GET['group_id'] ?? 0);
    if (!$groupId) { echo json_encode(['ok'=>false,'error'=>'Missing group_id']); exit; }
    $pdo = db();

    // Group schedule for sessions_due
    $g = $pdo->prepare("SELECT start_date, schedule_json FROM class_groups WHERE id = ?");
    $g->execute([$groupId]);
    $group = $g->fetch();
    $sessDue = $group ? computeSessionsDue($group['start_date'], $group['schedule_json']) : 0;

    // Sessions conducted for this group
    $sc = $pdo->prepare("
        SELECT COUNT(DISTINCT a.session_num) as cnt
        FROM attendance a
        JOIN class_group_members mt ON mt.user_id = a.teacher_id AND mt.group_id = ?
        JOIN class_group_members ms ON ms.user_id = a.student_id AND ms.group_id = ?
    ");
    $sc->execute([$groupId, $groupId]);
    $sessDone = (int)($sc->fetchColumn() ?: 0);

    // Per-student breakdown
    $stmt = $pdo->prepare("
        SELECT us.id as student_id,
               COALESCE(us.full_name, us.username) as student_name,
               COUNT(DISTINCT CASE WHEN a.present = 1 THEN a.session_num END) as present_count
        FROM class_group_members ms
        JOIN users us ON us.id = ms.user_id AND us.role = 'student'
        LEFT JOIN class_group_members mt ON mt.group_id = ms.group_id
        LEFT JOIN users ut ON ut.id = mt.user_id AND ut.role = 'teacher'
        LEFT JOIN attendance a ON a.student_id = ms.user_id AND a.teacher_id = ut.id
        WHERE ms.group_id = ?
        GROUP BY us.id, us.full_name, us.username
        ORDER BY us.full_name, us.username
    ");
    $stmt->execute([$groupId]);
    $students = $stmt->fetchAll();

    echo json_encode([
        'ok'                 => true,
        'sessions_conducted' => $sessDone,
        'sessions_due'       => $sessDue,
        'students'           => array_map(fn($s) => [
            'student_id'   => (int)$s['student_id'],
            'student_name' => $s['student_name'],
            'present_count'=> (int)$s['present_count'],
        ], $students),
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['ok'=>false,'error'=>'Unknown action']);
