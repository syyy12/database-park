<?php
session_start();
include 'db.php';

// 로그인 여부 확인
if (!isset($_SESSION['login_id'])) {
    header("Location: login.php");
    exit();
}

// 현재 로그인된 사용자 ID 가져오기
$current_user_id = $_SESSION['login_id'];

// POST와 PROJECT ID 확인
$post_id = $_GET['post_id'] ?? null;
$project_id = $_GET['project_id'] ?? null;

// 게시글 정보 확인
if (!$post_id || !$project_id) {
    die("잘못된 접근입니다.");
}

// 게시글 조회
$postQuery = "
    SELECT p.title, p.created_date, p.updated_date, u.user_name
    FROM Post AS p
    JOIN User AS u ON p.login_id = u.login_id
    WHERE p.id = ? AND p.project_id = ?
";
$postStmt = $conn->prepare($postQuery);
$postStmt->bind_param("ii", $post_id, $project_id);
$postStmt->execute();
$postResult = $postStmt->get_result();
$post = $postResult->fetch_assoc();

if (!$post) {
    die("게시글을 찾을 수 없습니다.");
}

// 1. 시스템 관리자 여부 확인 (User 테이블에서 role이 1인지 확인)
$systemAdminQuery = "
    SELECT role 
    FROM User 
    WHERE login_id = ?
";
$systemAdminStmt = $conn->prepare($systemAdminQuery);
$systemAdminStmt->bind_param("s", $current_user_id);
$systemAdminStmt->execute();
$systemAdminResult = $systemAdminStmt->get_result();
$is_system_admin = $systemAdminResult->fetch_assoc()['role'] == 1;

// 2. 프로젝트 관리자 여부 확인 (project_member 테이블에서 project_role이 1인지 확인)
$projectManagerQuery = "
    SELECT COUNT(*) AS is_manager
    FROM project_member
    WHERE project_id = ? AND login_id = ? AND project_role = 1
";
$projectManagerStmt = $conn->prepare($projectManagerQuery);
$projectManagerStmt->bind_param("is", $project_id, $current_user_id);
$projectManagerStmt->execute();
$projectManagerResult = $projectManagerStmt->get_result();
$is_project_manager = $projectManagerResult->fetch_assoc()['is_manager'] == 1;

// 3. 게시글 작성자인지 확인
$authorQuery = "
    SELECT login_id
    FROM Post
    WHERE id = ? AND project_id = ?
";
$authorStmt = $conn->prepare($authorQuery);
$authorStmt->bind_param("ii", $post_id, $project_id);
$authorStmt->execute();
$authorResult = $authorStmt->get_result();
$is_author = $authorResult->fetch_assoc()['login_id'] === $current_user_id;

// 삭제 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // 게시글 삭제
    $deleteQuery = "DELETE FROM Post WHERE id = ? AND project_id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("ii", $post_id, $project_id);
    $deleteStmt->execute();

    // 페이지 리다이렉션
    if ($is_system_admin || $is_project_manager) {
        header("Location: m_post.php?project_id=$project_id");
    } else {
        header("Location: post.php?post_id=$post_id&project_id=$project_id");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>게시글 삭제 확인</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        h2 {
            font-size: 24px;
            color: #d9534f;
            margin-bottom: 20px;
        }

        .post-info {
            margin-bottom: 20px;
        }

        .post-info p {
            font-size: 16px;
            color: #333;
        }

        .buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        button {
            padding: 10px 20px;
            font-size: 16px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button.confirm {
            background-color: #d9534f;
        }

        button.confirm:hover {
            background-color: #c9302c;
        }

        button.cancel {
            background-color: #5bc0de;
        }

        button.cancel:hover {
            background-color: #31b0d5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>게시글 삭제 확인</h2>
        <div class="post-info">
            <p><strong>제목:</strong> <?php echo htmlspecialchars($post['title']); ?></p>
            <p><strong>작성자:</strong> <?php echo htmlspecialchars($post['user_name']); ?></p>
            <p><strong>작성일:</strong> <?php echo htmlspecialchars($post['created_date']); ?></p>
            <p><strong>수정일:</strong> <?php echo htmlspecialchars($post['updated_date'] ?? '없음'); ?></p>
        </div>
        <form method="POST">
            <div class="buttons">
                <button type="submit" name="confirm_delete" class="confirm">삭제</button>
                <button type="button" class="cancel" onclick="location.href='m_post.php?project_id=<?php echo $project_id; ?>'">취소</button>
            </div>
        </form>
    </div>
</body>
</html>
