<?php
/**
 * api_quiz.php — Quiz system
 * Actions: list_quizzes, get_quiz, create_quiz, submit_quiz, quiz_results, toggle_quiz
 */
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']);
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body   = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];
$action = trim($_GET['action'] ?? ($body['action'] ?? ''));

if ($method === 'POST') csrf_verify();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rate_limit.php';
api_rate_limit('quiz:' . $uid, 60, 60);

function qErr(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok'=>false,'error'=>$msg]);
    exit;
}

try {
    $pdo = db();

    // ── Schema ────────────────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS quizzes (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title          VARCHAR(200) NOT NULL,
        description    TEXT,
        group_id       INT UNSIGNED NOT NULL,
        created_by     INT NOT NULL,
        time_limit_min TINYINT UNSIGNED DEFAULT 0,
        is_active      TINYINT(1) DEFAULT 1,
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_group (group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_questions (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quiz_id    INT UNSIGNED NOT NULL,
        question   TEXT NOT NULL,
        sort_order TINYINT UNSIGNED DEFAULT 0,
        INDEX idx_quiz (quiz_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_options (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        question_id INT UNSIGNED NOT NULL,
        option_text VARCHAR(400) NOT NULL,
        is_correct  TINYINT(1) DEFAULT 0,
        sort_order  TINYINT UNSIGNED DEFAULT 0,
        INDEX idx_question (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_attempts (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quiz_id     INT UNSIGNED NOT NULL,
        student_id  INT NOT NULL,
        score       TINYINT UNSIGNED DEFAULT 0,
        total       TINYINT UNSIGNED DEFAULT 0,
        finished_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_attempt (quiz_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── list_quizzes ──────────────────────────────────────────────────────────
    if ($action === 'list_quizzes') {
        if ($role === 'student') {
            $gr = $pdo->prepare("SELECT group_id FROM class_group_members WHERE user_id = ?");
            $gr->execute([$uid]);
            $gids = array_column($gr->fetchAll(), 'group_id');
            if (empty($gids)) { echo json_encode(['ok'=>true,'quizzes'=>[]]); exit; }
            $ph  = implode(',', array_fill(0, count($gids), '?'));
            $stmt = $pdo->prepare("
                SELECT q.id, q.title, q.description, q.time_limit_min, q.created_at,
                       (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=q.id) AS question_count,
                       a.score, a.total, a.finished_at AS attempted_at
                FROM quizzes q
                LEFT JOIN quiz_attempts a ON a.quiz_id=q.id AND a.student_id=?
                WHERE q.group_id IN ($ph) AND q.is_active=1
                ORDER BY q.created_at DESC");
            $stmt->execute(array_merge([$uid], $gids));
        } elseif (in_array($role, ['teacher','admin'])) {
            if ($role === 'teacher') {
                $gr = $pdo->prepare("SELECT group_id FROM class_group_members WHERE user_id = ?");
                $gr->execute([$uid]);
                $gids = array_column($gr->fetchAll(), 'group_id');
                if (empty($gids)) { echo json_encode(['ok'=>true,'quizzes'=>[]]); exit; }
                $ph  = implode(',', array_fill(0, count($gids), '?'));
                $stmt = $pdo->prepare("
                    SELECT q.id, q.title, q.description, q.time_limit_min, q.is_active, q.created_at,
                           g.group_letter, g.type_key,
                           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=q.id) AS question_count,
                           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=q.id) AS attempt_count,
                           (SELECT ROUND(AVG(score/total*100)) FROM quiz_attempts WHERE quiz_id=q.id AND total>0) AS avg_score
                    FROM quizzes q
                    JOIN class_groups g ON g.id=q.group_id
                    WHERE q.group_id IN ($ph)
                    ORDER BY q.created_at DESC");
                $stmt->execute($gids);
            } else {
                $stmt = $pdo->query("
                    SELECT q.id, q.title, q.description, q.time_limit_min, q.is_active, q.created_at,
                           g.group_letter, g.type_key,
                           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=q.id) AS question_count,
                           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=q.id) AS attempt_count,
                           (SELECT ROUND(AVG(score/total*100)) FROM quiz_attempts WHERE quiz_id=q.id AND total>0) AS avg_score
                    FROM quizzes q
                    JOIN class_groups g ON g.id=q.group_id
                    ORDER BY q.created_at DESC");
            }
        } else {
            qErr('Unauthorized', 403);
        }
        echo json_encode(['ok'=>true,'quizzes'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── get_quiz ──────────────────────────────────────────────────────────────
    if ($action === 'get_quiz') {
        $qid = (int)($_GET['id'] ?? 0);
        if (!$qid) qErr('id required');
        $quiz = $pdo->prepare("SELECT * FROM quizzes WHERE id=?");
        $quiz->execute([$qid]);
        $row = $quiz->fetch(PDO::FETCH_ASSOC);
        if (!$row) qErr('Quiz not found', 404);
        if (!$row['is_active'] && $role === 'student') qErr('Quiz not available', 403);

        if ($role === 'student') {
            $acc = $pdo->prepare("SELECT 1 FROM class_group_members WHERE group_id=? AND user_id=?");
            $acc->execute([$row['group_id'], $uid]);
            if (!$acc->fetch()) qErr('Access denied', 403);
            $att = $pdo->prepare("SELECT score,total FROM quiz_attempts WHERE quiz_id=? AND student_id=?");
            $att->execute([$qid, $uid]);
            $done = $att->fetch(PDO::FETCH_ASSOC);
            if ($done) {
                echo json_encode(['ok'=>true,'already_done'=>true,'score'=>(int)$done['score'],'total'=>(int)$done['total']]);
                exit;
            }
        }

        $qs = $pdo->prepare("SELECT id, question, sort_order FROM quiz_questions WHERE quiz_id=? ORDER BY sort_order");
        $qs->execute([$qid]);
        $questions = $qs->fetchAll(PDO::FETCH_ASSOC);
        $showCorrect = in_array($role, ['teacher','admin']);
        foreach ($questions as &$q) {
            $cols = $showCorrect ? 'id, option_text, is_correct, sort_order' : 'id, option_text, sort_order';
            $opts = $pdo->prepare("SELECT $cols FROM quiz_options WHERE question_id=? ORDER BY sort_order");
            $opts->execute([$q['id']]);
            $q['options'] = $opts->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($q);
        echo json_encode(['ok'=>true,'quiz'=>$row,'questions'=>$questions]);
        exit;
    }

    // ── create_quiz ───────────────────────────────────────────────────────────
    if ($action === 'create_quiz') {
        if (!in_array($role, ['teacher','admin'])) qErr('Unauthorized', 403);
        $title     = trim($body['title'] ?? '');
        $desc      = trim($body['description'] ?? '');
        $groupId   = (int)($body['group_id'] ?? 0);
        $timeLimit = max(0, (int)($body['time_limit_min'] ?? 0));
        $questions = $body['questions'] ?? [];
        if (!$title)   qErr('title required');
        if (!$groupId) qErr('group_id required');
        if (!is_array($questions) || count($questions) < 1) qErr('At least 1 question required');
        if (count($questions) > 50) qErr('Max 50 questions');
        if ($role === 'teacher') {
            $acc = $pdo->prepare("SELECT 1 FROM class_group_members WHERE group_id=? AND user_id=?");
            $acc->execute([$groupId, $uid]);
            if (!$acc->fetch()) qErr('Access denied', 403);
        }
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO quizzes (title,description,group_id,created_by,time_limit_min) VALUES (?,?,?,?,?)")
            ->execute([$title, $desc, $groupId, $uid, $timeLimit]);
        $newId = (int)$pdo->lastInsertId();
        $insQ = $pdo->prepare("INSERT INTO quiz_questions (quiz_id,question,sort_order) VALUES (?,?,?)");
        $insO = $pdo->prepare("INSERT INTO quiz_options (question_id,option_text,is_correct,sort_order) VALUES (?,?,?,?)");
        foreach ($questions as $i => $q) {
            $qText = trim($q['question'] ?? '');
            if (!$qText) continue;
            $insQ->execute([$newId, $qText, $i]);
            $qRowId = (int)$pdo->lastInsertId();
            foreach (($q['options'] ?? []) as $j => $opt) {
                $oText = trim($opt['text'] ?? '');
                if (!$oText) continue;
                $insO->execute([$qRowId, $oText, !empty($opt['correct']) ? 1 : 0, $j]);
            }
        }
        $pdo->commit();
        // Notify students
        try {
            $members = $pdo->prepare("SELECT cgm.user_id FROM class_group_members cgm JOIN users u ON u.id=cgm.user_id WHERE cgm.group_id=? AND u.role='student'");
            $members->execute([$groupId]);
            $ni = $pdo->prepare("INSERT IGNORE INTO notifications (user_id,type,title_fr,title_ar,body_fr,body_ar) VALUES (?,'quiz',?,?,?,?)");
            foreach ($members->fetchAll(PDO::FETCH_COLUMN) as $sid) {
                $ni->execute([$sid, '🧠 Nouveau quiz', '🧠 اختبار جديد', $title, $title]);
            }
        } catch (Throwable $e2) {}
        echo json_encode(['ok'=>true,'id'=>$newId]);
        exit;
    }

    // ── submit_quiz ───────────────────────────────────────────────────────────
    if ($action === 'submit_quiz') {
        if ($role !== 'student') qErr('Student only', 403);
        $quizId  = (int)($body['quiz_id'] ?? 0);
        $answers = $body['answers'] ?? [];
        if (!$quizId) qErr('quiz_id required');
        $quiz = $pdo->prepare("SELECT group_id FROM quizzes WHERE id=? AND is_active=1");
        $quiz->execute([$quizId]);
        $qRow = $quiz->fetch();
        if (!$qRow) qErr('Quiz not found', 404);
        $acc = $pdo->prepare("SELECT 1 FROM class_group_members WHERE group_id=? AND user_id=?");
        $acc->execute([$qRow['group_id'], $uid]);
        if (!$acc->fetch()) qErr('Access denied', 403);
        $att = $pdo->prepare("SELECT id FROM quiz_attempts WHERE quiz_id=? AND student_id=?");
        $att->execute([$quizId, $uid]);
        if ($att->fetch()) qErr('Already completed');
        // Score
        $qs = $pdo->prepare("SELECT id FROM quiz_questions WHERE quiz_id=?");
        $qs->execute([$quizId]);
        $qids = array_column($qs->fetchAll(), 'id');
        $total = count($qids);
        $score = 0;
        foreach ($qids as $qid) {
            $sel = (int)($answers[$qid] ?? 0);
            if (!$sel) continue;
            $opt = $pdo->prepare("SELECT is_correct FROM quiz_options WHERE id=? AND question_id=?");
            $opt->execute([$sel, $qid]);
            $r = $opt->fetch();
            if ($r && $r['is_correct']) $score++;
        }
        $pdo->prepare("INSERT INTO quiz_attempts (quiz_id,student_id,score,total) VALUES (?,?,?,?)")
            ->execute([$quizId, $uid, $score, $total]);
        echo json_encode(['ok'=>true,'score'=>$score,'total'=>$total,'pct'=>$total>0?round($score/$total*100):0]);
        exit;
    }

    // ── quiz_results ──────────────────────────────────────────────────────────
    if ($action === 'quiz_results') {
        if (!in_array($role, ['teacher','admin'])) qErr('Unauthorized', 403);
        $qid = (int)($_GET['id'] ?? 0);
        if (!$qid) qErr('id required');
        $rows = $pdo->prepare("
            SELECT a.student_id, a.score, a.total, a.finished_at,
                   u.full_name, u.username,
                   IF(a.total>0, ROUND(a.score/a.total*100), 0) AS pct
            FROM quiz_attempts a JOIN users u ON u.id=a.student_id
            WHERE a.quiz_id=? ORDER BY a.finished_at DESC");
        $rows->execute([$qid]);
        echo json_encode(['ok'=>true,'results'=>$rows->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── toggle_quiz ───────────────────────────────────────────────────────────
    if ($action === 'toggle_quiz') {
        if (!in_array($role, ['teacher','admin'])) qErr('Unauthorized', 403);
        $qid = (int)($body['id'] ?? 0);
        if (!$qid) qErr('id required');
        $pdo->prepare("UPDATE quizzes SET is_active=1-is_active WHERE id=?")->execute([$qid]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    // ── delete_quiz ───────────────────────────────────────────────────────────
    if ($action === 'delete_quiz') {
        if (!in_array($role, ['teacher','admin'])) qErr('Unauthorized', 403);
        $qid = (int)($body['id'] ?? 0);
        if (!$qid) qErr('id required');
        $pdo->beginTransaction();
        // Delete options → questions → attempts → quiz
        $pdo->prepare("DELETE quiz_options FROM quiz_options JOIN quiz_questions ON quiz_questions.id=quiz_options.question_id WHERE quiz_questions.quiz_id=?")->execute([$qid]);
        $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id=?")->execute([$qid]);
        $pdo->prepare("DELETE FROM quiz_attempts WHERE quiz_id=?")->execute([$qid]);
        $pdo->prepare("DELETE FROM quizzes WHERE id=?")->execute([$qid]);
        $pdo->commit();
        echo json_encode(['ok'=>true]);
        exit;
    }

    qErr('Unknown action');

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('api_quiz.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Server error']);
}
