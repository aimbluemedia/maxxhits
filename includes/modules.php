<?php
// ============================================================
//  includes/modules.php — Module registry
//
//  The single source of truth for navigation, page titles and
//  the placeholder workspaces. Ported from the GrokBot 2.0
//  prototype spec. Modules with a 'page' are fully built; the
//  rest render as structured placeholder workspaces through
//  module.php?m=<slug> until their phase is implemented.
// ============================================================

/** Navigation groups → module slugs (prototype order). */
function mh_groups(): array {
    return [
        'Command'        => ['overview', 'agent-operations', 'tasks-alerts'],
        'Intelligence'   => ['trend-research', 'competitor-intelligence', 'social-listening', 'opportunities'],
        'Creative'       => ['content-studio', 'ugc-video-studio', 'creator-library', 'image-studio', 'media-library'],
        'Automation'     => ['linkedin-automation', 'reddit-automation', 'pinterest-automation', 'blog-automation',
                             'facebook-automation', 'instagram-automation', 'youtube-automation', 'tiktok-automation',
                             'x-automation', 'threads-automation'],
        'Publishing'     => ['visual-planner', 'campaigns', 'approval-center', 'publishing-queue', 'account-health'],
        'Engagement'     => ['social-inbox', 'dm-flow-builder', 'comment-management', 'customer-profiles', 'lead-pipeline'],
        'Outreach'       => ['relationship-discovery', 'relationship-tasks', 'influencer-discovery',
                             'creator-outreach', 'affiliate-programs', 'partner-performance'],
        'Growth'         => ['analytics', 'revenue-attribution', 'experiments', 'learning-lab', 'recommendations'],
        'Administration' => ['brands', 'knowledge-base', 'connected-accounts', 'compliance', 'usage-budgets',
                             'audit-log', 'users', 'settings'],
    ];
}

/**
 * Module metadata. Keys:
 *   label  — nav + page title
 *   page   — dedicated PHP page (null → module.php placeholder)
 *   admin  — admin-only nav item
 *   goal / workflow / entities / api / checks — prototype spec,
 *   shown on placeholder workspaces as the build guide.
 */
function mh_modules(): array {
    static $m = null;
    if ($m !== null) return $m;

    $m = [
        // ── Command ────────────────────────────────────────
        'overview' => ['label' => 'Overview', 'page' => 'dashboard.php',
            'goal' => 'Executive view of brand operations and exceptions'],
        'agent-operations' => ['label' => 'Agent operations',
            'goal' => 'Coordinate specialized AI roles with persistent jobs',
            'workflow' => 'Director assigns typed jobs; agent retrieves knowledge; validates structured response; stores evidence and cost',
            'entities' => 'Agent, AgentRun, PromptVersion, Job',
            'api' => 'POST /agent-runs; GET /agent-runs',
            'checks' => 'Budget reservation; retry bounded; no direct publishing privileges'],
        'tasks-alerts' => ['label' => 'Tasks & alerts',
            'goal' => 'Assign review and exception work',
            'workflow' => 'Deduplicate alerts; assign owner and due date; resolve with note',
            'entities' => 'Task, Alert',
            'api' => 'POST /tasks; PATCH /tasks/{id}',
            'checks' => 'Escalate overdue critical alerts; preserve history'],

        // ── Intelligence ───────────────────────────────────
        'trend-research' => ['label' => 'Trend research',
            'goal' => 'Find relevant current topics with evidence',
            'workflow' => 'Search authorized web sources; store URL/date; score relevance and freshness; create brief',
            'entities' => 'ResearchItem, Source, Brief',
            'api' => 'POST /research-runs; GET /research',
            'checks' => 'Reject unsourced claims; treat retrieved text as untrusted'],
        'competitor-intelligence' => ['label' => 'Competitor intelligence',
            'goal' => 'Track public competitor campaigns',
            'workflow' => 'Collect authorized public observations; compare formats and positioning; recommend original concepts',
            'entities' => 'Competitor, Observation',
            'api' => 'POST /competitor-observations',
            'checks' => 'No private scraping or copying assets'],
        'social-listening' => ['label' => 'Social listening',
            'goal' => 'Understand genuine audience questions',
            'workflow' => 'Ingest permitted mentions; classify sentiment and themes; route support',
            'entities' => 'Mention, Theme',
            'api' => 'GET /mentions; POST /themes',
            'checks' => 'No sensitive profiling; review adverse events'],
        'opportunities' => ['label' => 'Opportunities',
            'goal' => 'Prioritize actionable campaign ideas',
            'workflow' => 'Rank evidence-backed ideas by fit, effort, risk; approve into campaign',
            'entities' => 'Opportunity, Decision',
            'api' => 'POST /opportunities/{id}/accept',
            'checks' => 'Show evidence and uncertainty'],

        // ── Creative ───────────────────────────────────────
        'content-studio' => ['label' => 'Content studio', 'page' => 'content-studio.php',
            'goal' => 'Create original platform-specific copy with AI'],
        'ugc-video-studio' => ['label' => 'UGC video studio',
            'goal' => 'Produce disclosed synthetic creator videos and real authorized UGC',
            'workflow' => 'Script; storyboard; reference assets; async video jobs; stitch; captions and overlay; review output',
            'entities' => 'VideoProject, Scene, RenderJob, Asset',
            'api' => 'POST /video-projects; POST /video-projects/{id}/render',
            'checks' => 'Consent and license checks; no fake patient testimonials; verify captions and final frames'],
        'creator-library' => ['label' => 'Creator library',
            'goal' => 'Manage reusable authorized presenter identities',
            'workflow' => 'Record synthetic or real status, reference permissions, voice rights and approved use',
            'entities' => 'Creator, Consent, License',
            'api' => 'POST /creators; PATCH /creators/{id}',
            'checks' => 'No unauthorized likeness or voice cloning; revoke expired assets'],
        'image-studio' => ['label' => 'Image studio',
            'goal' => 'Generate branded imagery',
            'workflow' => 'Select brief and aspect ratio; request provider; save variants; inspect and approve',
            'entities' => 'ImageJob, Asset',
            'api' => 'POST /image-jobs',
            'checks' => 'Verify packaging and claims; preserve provenance'],
        'media-library' => ['label' => 'Media library',
            'goal' => 'Manage approved reusable assets',
            'workflow' => 'Upload; malware scan; metadata; renditions; rights expiry; search',
            'entities' => 'Asset, AssetLicense',
            'api' => 'POST /assets; GET /assets',
            'checks' => 'Private signed URLs; block expired rights'],

        // ── Publishing ─────────────────────────────────────
        'visual-planner' => ['label' => 'Visual planner',
            'goal' => 'Preview and schedule content on a calendar',
            'workflow' => 'Calendar/feed/grid; drag schedule; timezone; preview each network; submit version',
            'entities' => 'Post, Schedule, Preview',
            'api' => 'PATCH /posts/{id}/schedule; GET /calendar',
            'checks' => 'Rescheduling invalidates approval if material; no duplicate schedules'],
        'campaigns' => ['label' => 'Campaigns',
            'goal' => 'Group content around business objectives',
            'workflow' => 'Define goal and audience; link posts, creators, landing pages and experiments',
            'entities' => 'Campaign, CampaignItem',
            'api' => 'POST /campaigns',
            'checks' => 'No sensitive audience inference; approved destinations'],
        'approval-center' => ['label' => 'Approval center', 'page' => 'approvals.php',
            'goal' => 'Enforce version-specific human review before anything publishes'],
        'publishing-queue' => ['label' => 'Publishing queue', 'page' => 'queue.php',
            'goal' => 'Deliver authorized posts reliably through official integrations'],
        'account-health' => ['label' => 'Account health',
            'goal' => 'Track integration health and restrictions',
            'workflow' => 'Check token expiry, webhook status, errors and provider warnings; pause blocked account',
            'entities' => 'AccountHealth, Incident',
            'api' => 'GET /account-health; POST /accounts/{id}/pause',
            'checks' => 'No rate-limit bypass or browser bot fallback'],

        // ── Engagement ─────────────────────────────────────
        'social-inbox' => ['label' => 'Social inbox',
            'goal' => 'Manage genuine conversations and staff handoff',
            'workflow' => 'Ingest verified webhook; dedupe; classify; draft reply; check eligibility; send or assign',
            'entities' => 'Conversation, Message, Assignment',
            'api' => 'GET /conversations; POST /conversations/{id}/reply',
            'checks' => 'Messaging windows; no sensitive data in AI; stop on opt-out'],
        'dm-flow-builder' => ['label' => 'DM flow builder',
            'goal' => 'Configure event-driven DM workflows',
            'workflow' => 'Version trigger, condition, reply, wait and handoff nodes; simulate; approve; activate',
            'entities' => 'Flow, FlowVersion, FlowRun',
            'api' => 'POST /flows; POST /flows/{id}/simulate',
            'checks' => 'Only eligible inbound/opted-in events; check eligibility at send; no unsolicited drip'],
        'comment-management' => ['label' => 'Comment management',
            'goal' => 'Respond meaningfully to comments',
            'workflow' => 'Ingest comments; classify; propose unique response; staff review or approved eligible automation',
            'entities' => 'Comment, Reply',
            'api' => 'GET /comments; POST /comments/{id}/reply',
            'checks' => 'No bulk unsolicited replies; escalation for medical topics'],
        'customer-profiles' => ['label' => 'Customer profiles',
            'goal' => 'Maintain minimal consent-aware contact records',
            'workflow' => 'Link authorized identifiers; record preferences and support history; honor deletion',
            'entities' => 'Contact, Consent, Suppression',
            'api' => 'GET /contacts; PATCH /contacts/{id}',
            'checks' => 'No diagnosis or treatment data; retention and access controls'],
        'lead-pipeline' => ['label' => 'Lead pipeline',
            'goal' => 'Track genuine inbound sales inquiries',
            'workflow' => 'Qualify business or general inquiries; assign staff; track stage and outcome',
            'entities' => 'Lead, LeadEvent',
            'api' => 'POST /leads; PATCH /leads/{id}',
            'checks' => 'No health-based targeting; consent before marketing'],

        // ── Outreach ───────────────────────────────────────
        'relationship-discovery' => ['label' => 'Relationship discovery',
            'goal' => 'Find relevant public accounts for genuine networking',
            'workflow' => 'Use permitted discovery sources; score topical fit with evidence; shortlist for human review',
            'entities' => 'Prospect, FitEvidence',
            'api' => 'POST /relationship-searches; GET /prospects',
            'checks' => 'No follower scraping, private data or inferred health status'],
        'relationship-tasks' => ['label' => 'Relationship tasks',
            'goal' => 'Grow authentic community relationships',
            'workflow' => 'Review shortlist; staff decides whether to follow or engage; record outcome',
            'entities' => 'RelationshipTask, RelationshipEvent',
            'api' => 'POST /relationship-tasks; PATCH /relationship-tasks/{id}',
            'checks' => 'No automated follow/unfollow, follow quotas or purchased engagement'],
        'influencer-discovery' => ['label' => 'Influencer discovery',
            'goal' => 'Find potential creators and affiliates',
            'workflow' => 'Search permitted sources and creator applications; assess audience fit and brand safety; shortlist',
            'entities' => 'Influencer, FitScore, Evidence',
            'api' => 'POST /influencer-searches',
            'checks' => 'No inferred sensitive attributes; distinguish estimated and verified metrics'],
        'creator-outreach' => ['label' => 'Creator outreach',
            'goal' => 'Recruit creators for mutually beneficial partnerships',
            'workflow' => 'Draft tailored invitation; verify approved contact or opt-in; staff reviews; record reply',
            'entities' => 'Invitation, OutreachEvent, Suppression',
            'api' => 'POST /invitations; POST /invitations/{id}/review',
            'checks' => 'No bulk unsolicited DMs; honor declines; email compliance; no scraping contact details'],
        'affiliate-programs' => ['label' => 'Affiliate programs',
            'goal' => 'Manage creator offers, links and compensation',
            'workflow' => 'Create approved offer; sign terms; issue tracking link; record conversions; reconcile payout',
            'entities' => 'Partner, Offer, TrackingLink, Commission, Payout',
            'api' => 'POST /offers; POST /partners/{id}/links',
            'checks' => 'Legal review per product/state; disclosure required; no referral incentives without clearance'],
        'partner-performance' => ['label' => 'Partner performance',
            'goal' => 'Measure creator results and collaboration quality',
            'workflow' => 'Import verified aggregate clicks and transactions; reconcile refunds; assess quality',
            'entities' => 'PartnerMetric, CommissionLedger',
            'api' => 'GET /partner-metrics; POST /commissions/reconcile',
            'checks' => 'No payment from unverified events; separate pending and payable'],

        // ── Growth ─────────────────────────────────────────
        'analytics' => ['label' => 'Analytics',
            'goal' => 'Measure audience and content outcomes',
            'workflow' => 'Ingest authorized platform metrics; normalize by platform and time; compare cohorts',
            'entities' => 'MetricSnapshot, PostMetric',
            'api' => 'GET /analytics; POST /metric-sync',
            'checks' => 'Null for unavailable metrics; distinguish organic and paid'],
        'revenue-attribution' => ['label' => 'Revenue attribution',
            'goal' => 'Measure approved first-party business outcomes',
            'workflow' => 'Use non-sensitive campaign IDs and aggregate conversion reports; reconcile orders internally',
            'entities' => 'CampaignAttribution, AggregateConversion',
            'api' => 'GET /attribution',
            'checks' => 'No patient data or sensitive events sent to social or AI; attribution is not causality'],
        'experiments' => ['label' => 'Experiments',
            'goal' => 'Test specific creative hypotheses',
            'workflow' => 'Register hypothesis, primary metric and minimum sample; assign variants; evaluate',
            'entities' => 'Experiment, VariantResult',
            'api' => 'POST /experiments; POST /experiments/{id}/evaluate',
            'checks' => 'Organic tests are observational unless randomized; avoid premature winners'],
        'learning-lab' => ['label' => 'Learning lab',
            'goal' => 'Improve strategies using verified outcomes',
            'workflow' => 'Retrieve aggregated metrics; propose new prompt or workflow version; offline test; review; deploy with rollback',
            'entities' => 'LearningRun, PolicyVersion, Evaluation',
            'api' => 'POST /learning-runs; POST /policy-versions/{id}/evaluate',
            'checks' => 'No autonomous rule or budget changes; guard against prompt injection'],
        'recommendations' => ['label' => 'Recommendations',
            'goal' => 'Turn AI insights into accountable actions',
            'workflow' => 'Store evidence, confidence, predicted benefit and owner; accept/reject; track impact',
            'entities' => 'Recommendation, Decision',
            'api' => 'GET /recommendations; POST /recommendations/{id}/decision',
            'checks' => 'Require evidence, minimum sample and rollback for changes'],

        // ── Administration ─────────────────────────────────
        'brands' => ['label' => 'Brands', 'page' => 'brands.php',
            'goal' => 'Maintain the global brand directory and workspace selector'],
        'knowledge-base' => ['label' => 'Knowledge base',
            'goal' => 'Provide approved facts and reusable guidance',
            'workflow' => 'Ingest approved material; chunk and index; retrieve by brand; version, expire and review',
            'entities' => 'KnowledgeDocument, Chunk, ClaimVersion',
            'api' => 'POST /knowledge; POST /knowledge/{id}/approve',
            'checks' => 'Source and expiry required; untrusted text cannot grant permissions'],
        'connected-accounts' => ['label' => 'Connected accounts', 'page' => 'accounts.php',
            'goal' => 'Manage publishing destinations and their credentials'],
        'compliance' => ['label' => 'Compliance',
            'goal' => 'Central policy checks and review routes',
            'workflow' => 'Evaluate content and actions against versioned platform/brand rules; route ambiguous cases',
            'entities' => 'Policy, PolicyCheck, Incident',
            'api' => 'POST /policy-checks; GET /policies',
            'checks' => 'Fail closed for unknown policy; review medical claims and synthetic disclosures'],
        'usage-budgets' => ['label' => 'Usage & budgets',
            'goal' => 'Control AI costs and account usage',
            'workflow' => 'Reserve estimated cost before job; reconcile actual; stop on cap; track by agent and brand',
            'entities' => 'Budget, UsageEvent, Reservation',
            'api' => 'GET /usage; PATCH /budgets/{id}',
            'checks' => 'Atomic reservations; admin approval for increases'],
        'audit-log' => ['label' => 'Audit log', 'page' => 'audit.php',
            'goal' => 'Maintain immutable operational history'],
        'users' => ['label' => 'Users', 'page' => 'users.php', 'admin' => true,
            'goal' => 'Manage staff accounts and roles'],
        'settings' => ['label' => 'Settings', 'page' => 'settings.php', 'admin' => true,
            'goal' => 'System configuration, AI provider, scheduler and emergency stop'],
    ];

    // Automation modules share one dedicated page (automation.php?p=…)
    foreach (mh_automation_pages() as $slug => $cfg) {
        $m[$slug] = [
            'label' => $cfg['label'],
            'page'  => 'automation.php?p=' . $cfg['platform'],
            'goal'  => 'Configure ' . $cfg['title'] . ' automation: recurring AI research and draft creation on a brand-specific schedule',
        ];
    }
    return $m;
}

/** Automation nav slugs → platform keys. */
function mh_automation_pages(): array {
    return [
        'linkedin-automation'  => ['label' => 'LinkedIn Automation',  'platform' => 'linkedin',  'title' => 'LinkedIn'],
        'reddit-automation'    => ['label' => 'Reddit Automation',    'platform' => 'reddit',    'title' => 'Reddit'],
        'pinterest-automation' => ['label' => 'Pinterest Automation', 'platform' => 'pinterest', 'title' => 'Pinterest'],
        'blog-automation'      => ['label' => 'Blog Automation',      'platform' => 'wordpress', 'title' => 'Blog / WordPress'],
        'facebook-automation'  => ['label' => 'Facebook Automation',  'platform' => 'facebook',  'title' => 'Facebook'],
        'instagram-automation' => ['label' => 'Instagram Automation', 'platform' => 'instagram', 'title' => 'Instagram'],
        'youtube-automation'   => ['label' => 'YouTube Automation',   'platform' => 'youtube',   'title' => 'YouTube'],
        'tiktok-automation'    => ['label' => 'TikTok Automation',    'platform' => 'tiktok',    'title' => 'TikTok'],
        'x-automation'         => ['label' => 'X Automation',         'platform' => 'twitter',   'title' => 'X'],
        'threads-automation'   => ['label' => 'Threads Automation',   'platform' => 'threads',   'title' => 'Threads'],
    ];
}

/**
 * Platform registry.
 *   adapter: true → auto-publishing works through lib/adapters.
 *   adapter: false → drafts are created for manual handoff only.
 *   fields: credential fields a destination of this platform needs.
 */
function mh_platforms(): array {
    return [
        'wordpress' => ['label' => 'Blog / WordPress', 'adapter' => true,
            'fields' => [
                ['site_url',     'Site URL',             'https://yourblog.com',    'The WordPress site address.'],
                ['username',     'WP username',           'editor',                 'The WordPress user that will own the posts.'],
                ['app_password', 'Application password',  'xxxx xxxx xxxx xxxx',    'WP Admin → Users → Profile → Application Passwords.'],
            ]],
        'facebook' => ['label' => 'Facebook Page', 'adapter' => true,
            'fields' => [
                ['page_id',      'Page ID',               '123456789',              'Meta Business Suite → Page settings.'],
                ['access_token', 'Page access token',     'EAAB…',                  'Long-lived PAGE token with pages_manage_posts.'],
            ]],
        'instagram' => ['label' => 'Instagram Business', 'adapter' => true,
            'fields' => [
                ['ig_user_id',   'IG business account ID', '178414…',               'Instagram professional account linked to a FB page.'],
                ['access_token', 'Access token',           'EAAB…',                 'Token with instagram_content_publish. Posts REQUIRE an image URL.'],
            ]],
        'twitter' => ['label' => 'X / Twitter', 'adapter' => true,
            'fields' => [
                ['access_token', 'OAuth 2.0 access token', '…',                     'User token with tweet.write scope (expires — refresh on 401s).'],
            ]],
        'linkedin' => ['label' => 'LinkedIn', 'adapter' => true,
            'fields' => [
                ['author_urn',   'Author URN',             'urn:li:organization:12345', 'urn:li:organization:<id> for a page, urn:li:person:<id> for a profile.'],
                ['access_token', 'Access token',           '…',                     'Token with w_organization_social or w_member_social scope.'],
            ]],
        'reddit'    => ['label' => 'Reddit',    'adapter' => false, 'fields' => []],
        'pinterest' => ['label' => 'Pinterest', 'adapter' => false, 'fields' => []],
        'tiktok'    => ['label' => 'TikTok',    'adapter' => false, 'fields' => []],
        'youtube'   => ['label' => 'YouTube',   'adapter' => false, 'fields' => []],
        'threads'   => ['label' => 'Threads',   'adapter' => false, 'fields' => []],
    ];
}

/** Resolve a module slug to its href. */
function mh_module_href(string $slug, array $mod): string {
    return $mod['page'] ?? ('module.php?m=' . $slug);
}
