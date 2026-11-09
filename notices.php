<?php
require_once __DIR__ . '/config/db.php';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = $page && $page > 0 ? $page : 1;
$perPage = 10;
$searchPattern = '%' . $search . '%';

$countStmt = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) AS total
     FROM notices
     WHERE (? = "" OR title LIKE ? OR description LIKE ? OR notice_type LIKE ?)'
);
mysqli_stmt_bind_param(
    $countStmt,
    'ssss',
    $search,
    $searchPattern,
    $searchPattern,
    $searchPattern
);
mysqli_stmt_execute($countStmt);
$totalNotices = (int)mysqli_fetch_assoc(
    mysqli_stmt_get_result($countStmt)
)['total'];
mysqli_stmt_close($countStmt);

$totalPages = max(1, (int)ceil($totalNotices / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$noticesStmt = mysqli_prepare(
    $conn,
    'SELECT notice_id, title, description, notice_type, deadline, created_at
     FROM notices
     WHERE (? = "" OR title LIKE ? OR description LIKE ? OR notice_type LIKE ?)
     ORDER BY created_at DESC
     LIMIT ? OFFSET ?'
);
mysqli_stmt_bind_param(
    $noticesStmt,
    'ssssii',
    $search,
    $searchPattern,
    $searchPattern,
    $searchPattern,
    $perPage,
    $offset
);
mysqli_stmt_execute($noticesStmt);
$noticesResult = mysqli_stmt_get_result($noticesStmt);

function noticesPageUrl(int $page, string $search): string
{
    $params = ['page' => $page];
    if ($search !== '') {
        $params['q'] = $search;
    }

    return '/CampusMate/notices.php?' . http_build_query($params);
}

include __DIR__ . '/includes/header.php';
?>

<main class="notices-page">
    <header class="notices-page-heading">
        <span class="notices-eyebrow">CAMPUS UPDATES</span>
        <h1>All <span>Notices</span></h1>
        <p>Browse the latest academic announcements, important updates, and deadlines.</p>
    </header>

    <form class="notices-search" method="GET" action="notices.php" role="search">
        <label class="visually-hidden" for="notice-search">Search notices</label>
        <input
            id="notice-search"
            type="search"
            name="q"
            value="<?= e($search) ?>"
            placeholder="Search title, details, or category..."
        >
        <button type="submit">Search</button>
        <?php if ($search !== ''): ?>
            <a class="notices-clear" href="notices.php">Clear</a>
        <?php endif; ?>
    </form>

    <p class="notices-result-count">
        <?= $totalNotices ?> <?= $totalNotices === 1 ? 'notice' : 'notices' ?>
        <?= $search !== '' ? 'found' : 'published' ?>
    </p>

    <section class="notices-list" aria-label="Published notices">
        <?php if ($totalNotices === 0): ?>
            <div class="notices-empty">
                <span aria-hidden="true">📢</span>
                <h2><?= $search !== '' ? 'No matching notices' : 'No notices yet' ?></h2>
                <p>
                    <?= $search !== ''
                        ? 'Try another search term.'
                        : 'New campus announcements will appear here.' ?>
                </p>
            </div>
        <?php else: ?>
            <?php while ($notice = mysqli_fetch_assoc($noticesResult)): ?>
                <?php
                $description = strip_tags($notice['description']);
                if (function_exists('mb_strimwidth')) {
                    $description = mb_strimwidth(
                        $description,
                        0,
                        220,
                        '…',
                        'UTF-8'
                    );
                }
                ?>
                <a
                    class="notice-entry"
                    href="notice.php?id=<?= (int)$notice['notice_id'] ?>"
                >
                    <span class="notice-entry-icon" aria-hidden="true">📢</span>
                    <span class="notice-entry-content">
                        <span class="notice-entry-meta">
                            <span class="notice-entry-type">
                                <?= e($notice['notice_type'] ?: 'Campus Notice') ?>
                            </span>
                            <span>Published <?= e(date('M d, Y', strtotime($notice['created_at']))) ?></span>
                        </span>
                        <strong class="notice-entry-title"><?= e($notice['title']) ?></strong>
                        <span class="notice-entry-description">
                            <?= e($description) ?>
                        </span>
                        <?php if (!empty($notice['deadline'])): ?>
                            <span class="notice-entry-deadline">
                                Deadline: <?= e(date('M d, Y', strtotime($notice['deadline']))) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <span class="notice-entry-arrow" aria-hidden="true">→</span>
                </a>
            <?php endwhile; ?>
        <?php endif; ?>
    </section>

    <?php if ($totalPages > 1): ?>
        <nav class="notices-pagination" aria-label="Notice pages">
            <?php if ($page > 1): ?>
                <a href="<?= e(noticesPageUrl($page - 1, $search)) ?>">← Previous</a>
            <?php endif; ?>

            <span>Page <?= $page ?> of <?= $totalPages ?></span>

            <?php if ($page < $totalPages): ?>
                <a href="<?= e(noticesPageUrl($page + 1, $search)) ?>">Next →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</main>

<style>
    .notices-page {
        width: min(950px, 92%);
        min-height: 60vh;
        margin: 48px auto 60px;
    }

    .notices-page-heading {
        margin-bottom: 25px;
        text-align: center;
    }

    .notices-eyebrow {
        color: #6366f1;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1.4px;
    }

    .notices-page-heading h1 {
        margin: 9px 0;
        color: #172033;
        font-size: clamp(30px, 5vw, 42px);
    }

    .notices-page-heading h1 span {
        color: #6366f1;
    }

    .notices-page-heading p {
        margin: 0;
        color: #64748b;
        line-height: 1.7;
    }

    .notices-search {
        display: flex;
        gap: 9px;
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #fff;
    }

    .notices-search input {
        flex: 1;
        min-width: 0;
        padding: 11px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font: inherit;
    }

    .notices-search button,
    .notices-clear {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 15px;
        border: 0;
        border-radius: 8px;
        background: #4f46e5;
        color: #fff;
        font: inherit;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }

    .notices-clear {
        background: #eef2ff;
        color: #4338ca;
    }

    .visually-hidden {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .notices-result-count {
        margin: 18px 2px 10px;
        color: #64748b;
        font-size: 13px;
    }

    .notices-list {
        display: grid;
        gap: 12px;
    }

    .notice-entry {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        padding: 19px;
        border: 1px solid #e5e7eb;
        border-radius: 13px;
        background: #fff;
        color: inherit;
        text-decoration: none;
        transition: border-color .2s, box-shadow .2s, transform .2s;
    }

    .notice-entry:hover {
        transform: translateY(-2px);
        border-color: #c7d2fe;
        box-shadow: 0 9px 24px rgba(31, 41, 55, .07);
    }

    .notice-entry:focus-visible {
        outline: 3px solid #6366f1;
        outline-offset: 3px;
    }

    .notice-entry-icon {
        display: grid;
        flex: 0 0 44px;
        width: 44px;
        height: 44px;
        place-items: center;
        border-radius: 11px;
        background: #eef2ff;
        font-size: 21px;
    }

    .notice-entry-content {
        display: grid;
        flex: 1;
        min-width: 0;
        gap: 7px;
    }

    .notice-entry-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 13px;
        align-items: center;
        color: #64748b;
        font-size: 12px;
    }

    .notice-entry-type {
        color: #4f46e5;
        font-size: 11px;
        font-weight: 750;
        text-transform: uppercase;
    }

    .notice-entry-title {
        color: #172554;
        font-size: 17px;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .notice-entry-description {
        color: #64748b;
        font-size: 13px;
        line-height: 1.6;
        overflow-wrap: anywhere;
    }

    .notice-entry-deadline {
        color: #b45309;
        font-size: 12px;
        font-weight: 650;
    }

    .notice-entry-arrow {
        padding-top: 10px;
        color: #6366f1;
        font-size: 20px;
    }

    .notices-empty {
        padding: 40px 20px;
        border: 1px dashed #cbd5e1;
        border-radius: 13px;
        background: #fff;
        text-align: center;
    }

    .notices-empty > span {
        font-size: 36px;
    }

    .notices-empty h2 {
        margin: 10px 0 6px;
        color: #172554;
    }

    .notices-empty p {
        margin: 0;
        color: #64748b;
    }

    .notices-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 18px;
        margin-top: 24px;
        color: #64748b;
        font-size: 13px;
    }

    .notices-pagination a {
        color: #4f46e5;
        font-weight: 700;
        text-decoration: none;
    }

    @media (max-width: 560px) {
        .notices-page {
            margin: 28px auto 40px;
        }

        .notices-search {
            flex-wrap: wrap;
        }

        .notices-search input {
            flex-basis: 100%;
        }

        .notice-entry {
            gap: 11px;
            padding: 15px;
        }

        .notice-entry-icon {
            flex-basis: 38px;
            width: 38px;
            height: 38px;
        }

        .notice-entry-title {
            font-size: 15px;
        }
    }
</style>

<?php
mysqli_stmt_close($noticesStmt);
include __DIR__ . '/includes/footer.php';
?>
