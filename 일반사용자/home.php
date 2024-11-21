# 2024 11 21 16시 수정본 : 관리자 유무에 따라 m_view.php or view.php 구현 완료
#                         소제목 [공지],[답글] 내용 구현
<?php
session_start();
include 'db.php';

// 로그인 여부 확인
if (!isset($_SESSION['login_id'])) {
    header("Location: login.php");
    exit();
}

$login_id = $_SESSION['login_id'];
$user_name = $_SESSION['user_name'];

// 참여 중인 프로젝트 목록 조회
$projectQuery = "
    SELECT pr.id, pr.project_name
    FROM project AS pr
    JOIN project_member AS pm ON pr.id = pm.project_id
    WHERE pm.login_id = ?
";
$projectStmt = $conn->prepare($projectQuery);
$projectStmt->bind_param("s", $login_id);
$projectStmt->execute();
$projectResult = $projectStmt->get_result();

// 참여 중인 프로젝트의 게시글 목록 조회 (수정일 기준 내림차순 정렬)
$postQuery = "
    SELECT p.id, p.Post_id, p.title, p.created_date, p.updated_date, p.is_noticed, p.login_id AS author_id, pr.id AS project_id, pr.project_name, pm.project_role
    FROM Post AS p
    JOIN project AS pr ON p.project_id = pr.id
    JOIN project_member AS pm ON pr.id = pm.project_id
    WHERE pm.login_id = ?
    ORDER BY COALESCE(p.updated_date, p.created_date) DESC
";

$postStmt = $conn->prepare($postQuery);
$postStmt->bind_param("s", $login_id);
$postStmt->execute();
$postResult = $postStmt->get_result();

// 프로젝트 관리자 여부 확인
$managerQuery = "
    SELECT project_id
    FROM project_member
    WHERE login_id = ? AND project_role = 1
";
$managerStmt = $conn->prepare($managerQuery);
$managerStmt->bind_param("s", $login_id);
$managerStmt->execute();
$managerResult = $managerStmt->get_result();
$managedProjects = [];
while ($row = $managerResult->fetch_assoc()) {
    $managedProjects[] = $row['project_id'];
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>홈페이지</title>
    <style>
        /* 전체 레이아웃 */
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        .header {
            background-color: #004d99;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h2 {
            margin: 0;
            font-size: 28px;
        }
        .content {
            flex: 1;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .section h3 {
            color: #4CAF50;
            margin-bottom: 10px;
            font-size: 22px;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            margin-bottom: 10px;
        }
        a {
            color: #004d99;
            text-decoration: none;
            font-weight: bold;
        }
        a:hover {
            text-decoration: underline;
        }
        .logout-button {
            display: block;
            margin: 20px auto;
            width: 100%;
            max-width: 200px;
            padding: 10px 20px;
            text-align: center;
            font-size: 16px;
            color: white;
            background-color: #d9534f;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .logout-button:hover {
            background-color: #c9302c;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>(주) 영남대학</h2>
    </div>
    <div class="content">
       <!-- 프로젝트 목록 -->
        <div class="section">
            <h3>📂 프로젝트 목록</h3>
            <ul>
                <?php
                if ($projectResult->num_rows > 0) {
                    while ($project = $projectResult->fetch_assoc()) {
                        $projectName = htmlspecialchars($project['project_name']);
                        $projectId = $project['id'];
                        echo "<li><a href='project.php?project_id=$projectId'>$projectName</a></li>";
                    }
                } else {
                    echo "<li>참여 중인 프로젝트가 없습니다.</li>";
                }
                ?>
            </ul>
        </div>

       <!-- 게시글 목록 -->
        <div class="section">
            <h3>📝 전체 게시판</h3>
            <ul>
                <?php
                if ($postResult->num_rows > 0) {
                    while ($post = $postResult->fetch_assoc()) {
                        $postTitle = htmlspecialchars($post['title']);
                        $postId = $post['id']; // 게시글 ID
                        $postParentId = $post['Post_id']; // 답글의 원글 ID
                        $isNoticed = $post['is_noticed']; // 공지 여부
                        $projectId = $post['project_id']; // 프로젝트 ID
                        $projectName = htmlspecialchars($post['project_name']);
                        $createdDate = $post['created_date'];
                        $updatedDate = $post['updated_date'];
                        $displayDate = $updatedDate ?? $createdDate;
                        $isManager = $post['project_role'] == 1; // 매니저 여부 확인

                        // 공지사항 확인
                        if ($isNoticed) {
                            $postTitle = "[공지] $postTitle";
                        }

                        // 답글 여부를 확인하여 제목 변경
                        if ($postParentId) {
                            // 원글 제목 가져오기
                            $parentQuery = "SELECT title FROM post WHERE id = ?";
                            $parentStmt = $conn->prepare($parentQuery);
                            $parentStmt->bind_param("i", $postParentId);
                            $parentStmt->execute();
                            $parentResult = $parentStmt->get_result();

                            if ($parentResult->num_rows > 0) {
                                $parentRow = $parentResult->fetch_assoc();
                                $parentTitle = htmlspecialchars($parentRow['title']);
                                $postTitle = "[답글: $parentTitle] $postTitle";
                            }
                            $parentStmt->close();
                        }

                        // 매니저 여부에 따른 페이지 결정
                        $targetPage = $isManager ? "m_view_post.php" : "view_post.php";

                        // 게시글 출력
                        echo "<li><a href='$targetPage?post_id=$postId&project_id=$projectId'>$postTitle</a> - $projectName ($displayDate)</li>";
                    }
                } else {
                    echo "<li>게시글이 없습니다.</li>";
                }
                ?>
            </ul>
        </div>
    </div>

    <!-- 로그아웃 버튼 -->
    <button class="logout-button" onclick="location.href='logout.php'">로그아웃</button>
</body>
</html>
