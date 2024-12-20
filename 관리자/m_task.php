<?php
session_start();
include 'db.php';

// 로그인 여부 확인
if (!isset($_SESSION['login_id'])) {
    header("Location: login.php");
    exit();
}

$task_id = $_GET['task_id'] ?? null;
if (!$task_id) {
    die("잘못된 접근입니다.");
}

// 테스크 정보 조회
$taskQuery = "
    SELECT t.id AS task_id, t.task_name, t.description, t.start, t.end, t.project_id
    FROM task t
    WHERE t.id = ?
";
$taskStmt = $conn->prepare($taskQuery);
$taskStmt->bind_param("i", $task_id);
$taskStmt->execute();
$taskResult = $taskStmt->get_result();
$task = $taskResult->fetch_assoc();

if (!$task) {
    die("테스크 정보를 찾을 수 없습니다.");
}

// 서브 테스크 목록 및 관계 조회
$subTaskQuery = "
    SELECT st.id, st.sub_task_name, st.is_completed, st.min_days, st.start, st.end, st.pre_sub_task_id, pst.sub_task_name AS pre_task_name
    FROM sub_task AS st
    LEFT JOIN sub_task AS pst ON st.pre_sub_task_id = pst.id
    WHERE st.task_id = ?
    ORDER BY st.start ASC
";
$subTaskStmt = $conn->prepare($subTaskQuery);
$subTaskStmt->bind_param("i", $task_id);
$subTaskStmt->execute();
$subTaskResult = $subTaskStmt->get_result();

// 데이터 그룹화: 선행 관계에 따라 서브 테스크 정렬
$tasksById = [];
$chains = [];
$independentTasks = [];
while ($row = $subTaskResult->fetch_assoc()) {
    $tasksById[$row['id']] = $row;

    if (empty($row['pre_sub_task_id'])) {
        $independentTasks[$row['id']] = [$row];
    } else {
        $chains[$row['pre_sub_task_id']][] = $row['id'];
    }
}

// 정렬된 체인을 생성
$tasksByGroup = [];
$visited = [];
foreach ($independentTasks as $taskId => $taskGroup) {
    $currentChain = $taskGroup;
    $currentId = $taskId;

    while (isset($chains[$currentId])) {
        foreach ($chains[$currentId] as $nextTaskId) {
            if (!in_array($nextTaskId, $visited)) {
                $visited[] = $nextTaskId;
                $currentChain[] = $tasksById[$nextTaskId];
                $currentId = $nextTaskId;
            }
        }
    }
    $tasksByGroup[] = $currentChain;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($task['task_name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        h2 {
            font-size: 36px;
            color: #004d99;
            margin-bottom: 20px;
        }

        .info {
            margin-bottom: 30px;
        }

        .info p {
            font-size: 18px;
            line-height: 1.6;
            margin: 10px 0;
        }

        .task-diagram {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .task-row {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .task-box {
            text-align: center;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            min-width: 200px;
            position: relative;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .task-box:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .task-box a {
            text-decoration: none;
            color: inherit;
        }

        .task-box h4 {
            margin: 10px 0;
            color: #333;
        }

        .task-box .info {
            font-size: 14px;
            color: #555;
        }

        .task-arrow {
            font-size: 20px;
            color: #333;
        }

        .buttons {
            margin-top: 20px;
            text-align: right;
        }

        button {
            padding: 10px 20px;
            font-size: 16px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }

        button.primary {
            background-color: #004d99;
        }

        button.primary:hover {
            background-color: #003366;
        }

        button.secondary {
            background-color: #d9534f;
        }

        button.secondary:hover {
            background-color: #c9302c;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><?php echo htmlspecialchars($task['task_name']); ?></h2>
        <div class="info" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <p><strong>설명:</strong> <?php echo htmlspecialchars($task['description']); ?></p>
                <p><strong>시작일:</strong> <?php echo $task['start']; ?></p>
                <p><strong>종료일:</strong> <?php echo $task['end']; ?></p>
            </div>
            <button class="primary" onclick="location.href='m_sub_task_add.php?task_id=<?php echo $task_id; ?>'">
                서브 테스크 추가
            </button>
        </div>

        <div class="task-diagram">
            <?php foreach ($tasksByGroup as $group): ?>
                <div class="task-row">
                    <?php foreach ($group as $subTask): ?>
                        <div class="task-box">
                            <a href="m_sub_task.php?sub_task_id=<?php echo $subTask['id']; ?>&task_id=<?php echo $task_id; ?>">
                                <h4><?php echo htmlspecialchars($subTask['sub_task_name']); ?></h4>
                                <div class="info">
                                    <p>선행: <?php echo htmlspecialchars($subTask['pre_task_name'] ?? '없음'); ?></p>
                                    <p>최소 소요일: <?php echo $subTask['min_days']; ?>일</p>
                                    <p>시작일: <?php echo htmlspecialchars($subTask['start']); ?></p>
                                    <p>종료일: <?php echo htmlspecialchars($subTask['end']); ?></p>
                                </div>
                            </a>
                        </div>

                        <?php if (next($group)): ?>
                            <span class="task-arrow">➡</span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="buttons">
            <button class="primary" onclick="location.href='m_project.php?project_id=<?php echo $task['project_id']; ?>'">뒤로 가기</button>
        </div>
    </div>
</body>
</html>
