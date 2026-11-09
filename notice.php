<?php
require_once __DIR__ . '/config/db.php';

$noticeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$noticeId || $noticeId < 1) {
    http_response_code(404);
    $notice = null;
} else {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT title, description, notice_type, deadline, created_at
         FROM notices
         WHERE notice_id = ?
         LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'i', $noticeId);
    mysqli_stmt_execute($stmt);
    $notice = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);

    if (!$notice) {
        http_response_code(404);
    }
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

include __DIR__ . '/includes/header.php';
?>

<main class="notice-detail-page">
    <?php if ($notice): ?>
        <article class="notice-detail-card">
            <a class="notice-back-link" href="/CampusMate/notices.php">
                &larr; All notices
            </a>

            <div class="notice-detail-meta">
                <span class="notice-detail-type">
                    <?= e($notice['notice_type'] ?: 'Campus Notice') ?>
                </span>
                <span>
                    Published <?= e(date('M d, Y', strtotime($notice['created_at']))) ?>
                </span>
            </div>

            <h1><?= e($notice['title']) ?></h1>

            <?php if (!empty($notice['deadline'])): ?>
                <p class="notice-deadline">
                    <strong>Deadline:</strong>
                    <?= e(date('M d, Y', strtotime($notice['deadline']))) ?>
                </p>
            <?php endif; ?>

            <div class="notice-detail-description">
                <?= nl2br(e($notice['description'])) ?>
            </div>
        </article>
    <?php else: ?>
        <section class="notice-detail-card notice-not-found">
            <div class="notice-detail-icon">📢</div>
            <h1>Notice not found</h1>
            <p>This notice may have been removed or the link may be incorrect.</p>
            <a class="notice-back-button" href="/CampusMate/notices.php">
                Browse notices
            </a>
        </section>
    <?php endif; ?>
</main>

<style>
    .notice-detail-page {
        width: min(900px, 92%);
        min-height: 55vh;
        margin: 45px auto;
    }

    .notice-detail-card {
        padding: clamp(24px, 5vw, 48px);
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 15px 40px rgba(31, 41, 55, .07);
    }

    .notice-back-link {
        display: inline-block;
        margin-bottom: 28px;
        color: #4f46e5;
        font-size: 14px;
        font-weight: 700;
    }

    .notice-detail-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        color: #64748b;
        font-size: 13px;
    }

    .notice-detail-type {
        padding: 5px 10px;
        border-radius: 30px;
        background: #eef2ff;
        color: #4338ca;
        font-weight: 700;
    }

    .notice-detail-card h1 {
        margin: 18px 0;
        color: #172554;
        font-size: clamp(27px, 5vw, 38px);
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .notice-deadline {
        margin: 0 0 22px;
        color: #b45309;
    }

    .notice-detail-description {
        padding-top: 22px;
        border-top: 1px solid #e5e7eb;
        color: #475569;
        font-size: 16px;
        line-height: 1.9;
        overflow-wrap: anywhere;
    }

    .notice-not-found {
        text-align: center;
    }

    .notice-not-found h1 {
        margin-bottom: 10px;
    }

    .notice-not-found p {
        margin: 0 0 22px;
        color: #64748b;
    }

    .notice-detail-icon {
        font-size: 42px;
    }

    .notice-back-button {
        display: inline-block;
        padding: 11px 16px;
        border-radius: 8px;
        background: #4f46e5;
        color: #fff;
        font-weight: 700;
    }

    @media (max-width: 600px) {
        .notice-detail-page {
            margin: 25px auto;
        }
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
