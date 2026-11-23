<?php
require_once __DIR__ . '/../includes/student_auth.php';
campusMateRequireAccess('opportunities');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../config/db.php';

function opportunityPageEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function opportunityApplicationUrl(?string $url): ?string
{
    if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : null;
}

$opportunitiesResult = mysqli_query(
    $conn,
    'SELECT opportunity_id, title, `type`, organization, description,
            application_link, deadline, created_at
     FROM opportunities
     WHERE approval_status = \'Approved\'
     ORDER BY created_at DESC'
);
$opportunityCount = mysqli_num_rows($opportunitiesResult);

include __DIR__ . '/../includes/header.php';
?>

<main class="opportunities-page">
    <header class="opportunities-page-heading">
        <span class="opportunities-eyebrow">YOUR NEXT STEP</span>
        <h1>Career <span>Opportunities</span></h1>
        <p>Explore internships, jobs, and scholarships shared by CampusMate admins and alumni.</p>
    </header>

    <p class="opportunities-count">
        <?= $opportunityCount ?>
        <?= $opportunityCount === 1 ? 'opportunity' : 'opportunities' ?> available
    </p>

    <section class="opportunities-list" aria-label="Available opportunities">
        <?php if ($opportunityCount === 0): ?>
            <div class="opportunities-empty">
                <span aria-hidden="true">💼</span>
                <h2>No opportunities yet</h2>
                <p>New internships, jobs, and scholarships will appear here.</p>
            </div>
        <?php else: ?>
            <?php while ($opportunity = mysqli_fetch_assoc($opportunitiesResult)): ?>
                <?php
                $applicationUrl = opportunityApplicationUrl(
                    $opportunity['application_link']
                );
                $typeClass = strtolower($opportunity['type']);
                ?>
                <article class="opportunity-entry">
                    <div class="opportunity-entry-top">
                        <span class="opportunity-entry-type <?= opportunityPageEscape($typeClass) ?>">
                            <?= opportunityPageEscape($opportunity['type']) ?>
                        </span>
                        <span class="opportunity-entry-date">
                            Posted <?= opportunityPageEscape(
                                date('M d, Y', strtotime($opportunity['created_at']))
                            ) ?>
                        </span>
                    </div>

                    <h2><?= opportunityPageEscape($opportunity['title']) ?></h2>
                    <p class="opportunity-organization">
                        <?= opportunityPageEscape($opportunity['organization']) ?>
                    </p>

                    <?php if (!empty($opportunity['description'])): ?>
                        <div class="opportunity-description">
                            <?= nl2br(opportunityPageEscape($opportunity['description'])) ?>
                        </div>
                    <?php endif; ?>

                    <div class="opportunity-entry-bottom">
                        <?php if (!empty($opportunity['deadline'])): ?>
                            <span class="opportunity-deadline">
                                Deadline:
                                <?= opportunityPageEscape(
                                    date('M d, Y', strtotime($opportunity['deadline']))
                                ) ?>
                            </span>
                        <?php else: ?>
                            <span class="opportunity-deadline">No deadline specified</span>
                        <?php endif; ?>

                        <?php if ($applicationUrl !== null): ?>
                            <a
                                class="opportunity-apply"
                                href="<?= opportunityPageEscape($applicationUrl) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Apply now <span aria-hidden="true">→</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php endif; ?>
    </section>
</main>

<style>
    .opportunities-page {
        width: min(950px, 92%);
        min-height: 55vh;
        margin: 48px auto 60px;
    }

    .opportunities-page-heading {
        margin-bottom: 25px;
        text-align: center;
    }

    .opportunities-eyebrow {
        color: #6366f1;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1.4px;
    }

    .opportunities-page-heading h1 {
        margin: 9px 0;
        color: #172033;
        font-size: clamp(30px, 5vw, 42px);
    }

    .opportunities-page-heading h1 span {
        color: #6366f1;
    }

    .opportunities-page-heading p {
        margin: 0;
        color: #64748b;
        line-height: 1.7;
    }

    .opportunities-count {
        margin: 18px 2px 10px;
        color: #64748b;
        font-size: 13px;
    }

    .opportunities-list {
        display: grid;
        gap: 14px;
    }

    .opportunity-entry,
    .opportunities-empty {
        padding: 22px;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(31, 41, 55, .05);
    }

    .opportunity-entry-top,
    .opportunity-entry-bottom {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .opportunity-entry-type {
        padding: 5px 10px;
        border-radius: 30px;
        background: #eef2ff;
        color: #4338ca;
        font-size: 11px;
        font-weight: 750;
        text-transform: uppercase;
    }

    .opportunity-entry-type.internship {
        background: #eff6ff;
        color: #2563eb;
    }

    .opportunity-entry-type.job {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .opportunity-entry-type.scholarship {
        background: #ecfdf5;
        color: #059669;
    }

    .opportunity-entry-date {
        color: #64748b;
        font-size: 12px;
    }

    .opportunity-entry h2 {
        margin: 15px 0 4px;
        color: #172554;
        font-size: 20px;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .opportunity-organization {
        margin: 0;
        color: #6366f1;
        font-size: 14px;
        font-weight: 650;
    }

    .opportunity-description {
        margin-top: 13px;
        color: #475569;
        font-size: 14px;
        line-height: 1.7;
        overflow-wrap: anywhere;
    }

    .opportunity-entry-bottom {
        padding-top: 16px;
        margin-top: 17px;
        border-top: 1px solid #e5e7eb;
    }

    .opportunity-deadline {
        color: #b45309;
        font-size: 13px;
        font-weight: 650;
    }

    .opportunity-apply {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 15px;
        border-radius: 8px;
        background: #4f46e5;
        color: #fff;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
    }

    .opportunities-empty {
        padding: 40px 20px;
        border-style: dashed;
        text-align: center;
        box-shadow: none;
    }

    .opportunities-empty > span {
        font-size: 36px;
    }

    .opportunities-empty h2 {
        margin: 10px 0 6px;
        color: #172554;
    }

    .opportunities-empty p {
        margin: 0;
        color: #64748b;
    }

    @media (max-width: 560px) {
        .opportunities-page {
            margin: 28px auto 40px;
        }

        .opportunity-entry {
            padding: 17px;
        }

        .opportunity-entry-bottom {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
