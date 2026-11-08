<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Public demo site of the CodeLab plugins (codelab.tsp.edu.rs).
 *
 * Put this file and codelab-demo.mbz / codelab-demo-sr.mbz in the Moodle folder, next to config.php (not in public/,
 * so the web cannot run it), and run it from the command line or a cron job:
 *   php demo.php setup   once: site settings, language packs, demo accounts, the demo course
 *   php demo.php reset   every night: the demo course from the backup again (posts, attempts and changes are gone)
 *
 * @copyright  2026 Boban Blagojević
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
if (file_exists(__DIR__ . '/config.php')) {
    require(__DIR__ . '/config.php');
} else if (file_exists(dirname(__DIR__) . '/config.php')) {
    require(dirname(__DIR__) . '/config.php');
} else {
    require('config.php');
}
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/filterlib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

const DEMO_SHORTNAME = 'CODELAB-DEMO';
const DEMO_PASSWORD = 'Demo-2026';
const DEMO_USERS = [
    'teacher' => ['firstname' => 'Demo', 'lastname' => 'Teacher', 'role' => 'editingteacher'],
    'student' => ['firstname' => 'Demo', 'lastname' => 'Student', 'role' => 'student'],
];

const DEMO_SUMMARIES = [
    'en' => 'Interactive coding and debugging directly in Moodle with CodeLab. Write, run, and step-debug Python, JavaScript, C, C++, Java, PHP, SQL, and HTML/CSS with instant browser output, interactive step debugger, and autograded coding quizzes.',
    'sr' => 'Interaktivno programiranje i otklanjanje grešaka u Moodle-u uz CodeLab. Pišite, pokrećite i koračno debagirajte Python, JavaScript, C, C++, Java, PHP, SQL i HTML/CSS uz trenutni izlaz, vizuelni debager i automatski ocenjivane zadatke.',
];

const DEMO_CARDS = [
    'en' => 'cards/card-en.png',
    'sr' => 'cards/card-sr.png',
];

$command = $argv[1] ?? '';
if (!in_array($command, ['setup', 'reset'], true)) {
    fwrite(STDERR, "Usage: php demo.php setup|reset\n");
    exit(1);
}
\core\session\manager::set_user(get_admin());

// Settings and CSS are always updated so design refinements apply on reset too.
demo_settings();
if ($command === 'setup') {
    demo_languages();
}

$en_mbz = __DIR__ . '/codelab-demo.mbz';
if (!is_readable($en_mbz)) {
    throw new moodle_exception('generalexceptionmessage', 'error', '', "Missing {$en_mbz}");
}
$en_id = demo_restore_course('CODELAB-DEMO', 'CodeLab: demo (English)', $en_mbz);
demo_users($en_id);
demo_set_course_overview($en_id, DEMO_SUMMARIES['en'], DEMO_CARDS['en']);

$sr_mbz = __DIR__ . '/codelab-demo-sr.mbz';
$sr_id = null;
if (is_readable($sr_mbz)) {
    $sr_id = demo_restore_course('CODELAB-DEMO-SR', 'CodeLab: demo (srpski)', $sr_mbz);
    demo_users($sr_id);
    demo_set_course_overview($sr_id, DEMO_SUMMARIES['sr'], DEMO_CARDS['sr']);
}

// Cross-link banners and front page
if ($sr_id !== null) {
    demo_ensure_admin_guide($en_id, 'en');
    demo_ensure_admin_guide($sr_id, 'sr');
    demo_section_banner($en_id, $sr_id, 'en');
    demo_section_banner($sr_id, $en_id, 'sr');
    demo_frontpage($en_id, $sr_id);
} else {
    demo_ensure_admin_guide($en_id, 'en');
    demo_section_banner_single($en_id);
    demo_frontpage_single($en_id);
}

purge_all_caches();
foreach (['jobeapikey' => 'Jobe API key', 'runnersecret' => 'Secret of the interactive server'] as $name => $label) {
    if (!get_config('tiny_codelab', $name)) {
        echo "Still to do: set {$label} in Site administration > Plugins > Text editors > TinyMCE editor > CodeLab\n";
    }
}
echo "Done English: {$CFG->wwwroot}/course/view.php?id={$en_id}\n";
if ($sr_id !== null) {
    echo "Done Serbian: {$CFG->wwwroot}/course/view.php?id={$sr_id}\n";
}

/**
 * Site settings of a public demo: no mail, no sign-up, guest button, all content cleaned, the CodeLab filter on, the
 * Jobe server and the CodeLab runner of TSP, modern custom CSS for theme_boost.
 */
function demo_settings(): void {
    global $CFG;

    $settings = [
        'noemailever' => 1,         // Nothing is ever mailed.
        'registerauth' => '',       // No self registration.
        'guestloginbutton' => 1,    // "Access as a guest" on the login page.
        'forceclean' => 1,          // All content is cleaned, so nobody can put a script on a page.
        'langmenu' => 1,
        'timezone' => 'Europe/Belgrade',
        'maxbytes' => 1048576,
        'messaging' => 0,
        'enableblogs' => 0,
        'enablecourserequests' => 0,
        'defaulthomepage' => 0,
    ];
    foreach ($settings as $name => $value) {
        set_config($name, $value);
    }
    filter_set_global_state('codelab', TEXTFILTER_ON);
    if (file_exists($CFG->dirroot . '/filter/algorithm/version.php')) {
        filter_set_global_state('algorithm', TEXTFILTER_ON);
    }
    if (file_exists($CFG->dirroot . '/filter/aisim/version.php')) {
        filter_set_global_state('aisim', TEXTFILTER_ON);
    }
    set_config('jobeserver', 'https://jobe.tsp.edu.rs', 'tiny_codelab');
    set_config('runnerurl', 'wss://runner.tsp.edu.rs', 'tiny_codelab');
    set_config('browserpython', 1, 'tiny_codelab');

    // Ensure none of the 3 subplugins are disabled in TinyMCE
    $disabled = get_config('editor_tiny', 'disabledsubplugins') ?: '';
    if (!empty($disabled)) {
        $parts = array_filter(array_map('trim', explode(',', $disabled)), function($p) {
            return !in_array($p, ['algorithm', 'codelab', 'aisim']);
        });
        set_config('disabledsubplugins', implode(',', $parts), 'editor_tiny');
    }

    // Demo login instructions on login/index.php
    $auth_instructions = <<<'HTML'
<div class="card border-0 shadow-lg rounded-4 p-4 text-start text-white" style="background: rgba(15, 23, 42, 0.92) !important; backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.18) !important; max-width: 440px; margin: 0 auto;">
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="fs-4">⚡</span>
        <h5 class="fw-bold mb-0 text-white">Instant Demo Access</h5>
    </div>
    <div class="p-3 rounded-3 mb-2" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.12);">
        <div class="fw-bold mb-1" style="color: #38bdf8;">🧑‍🏫 Teacher: <code>teacher</code></div>
        <div class="small" style="color: rgba(255, 255, 255, 0.9); line-height: 1.4;">Insert &amp; edit runnable Monaco code in TinyMCE (9 languages), configure test cases and auto-grading.</div>
    </div>
    <div class="p-3 rounded-3 mb-3" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.12);">
        <div class="fw-bold mb-1" style="color: #fbbf24;">🎓 Student: <code>student</code></div>
        <div class="small" style="color: rgba(255, 255, 255, 0.9); line-height: 1.4;">Run 8 languages + SQL with live stdin, step through C &amp; Python with debugger, solve coding quizzes.</div>
    </div>
    <div class="d-flex align-items-center justify-content-between pt-2 border-top border-white border-opacity-25 small mb-2">
        <span style="color: rgba(255, 255, 255, 0.9);">Password:</span>
        <code style="background: #000000; color: #fde047; padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 0.95rem; border: 1px solid rgba(253, 224, 71, 0.4);">Demo-2026</code>
    </div>
    <div class="text-center small mt-2" style="color: rgba(255, 255, 255, 0.75);">
        Or click the <strong>Log in as guest</strong> button below!
    </div>
</div>
HTML;
    set_config('auth_instructions', $auth_instructions);

    // Modern Boost and Moove theme custom CSS
    $customcss = <<<'CSS'
/* Modern Boost & Moove Theme Enhancements for CodeLab Demo */
:root {
    --bs-primary: #2563eb;
    --bs-primary-rgb: 37, 99, 235;
}
body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    color: #1e293b;
}
.card {
    border-radius: 14px;
    border: none;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}
.demo-course-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.demo-course-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 30px rgba(0,0,0,0.12) !important;
}
.badge {
    font-weight: 600;
    letter-spacing: 0.02em;
}
pre, code, kbd, samp {
    font-family: 'Fira Code', Consolas, 'Courier New', monospace;
}
.navbar-bootswatch, .navbar-light {
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
#page-footer {
    background: #0f172a;
    color: #94a3b8;
    padding-top: 2rem;
    padding-bottom: 2rem;
}
#page-footer a {
    color: #38bdf8;
}

/* Center login instructions card in Moove theme */
body.pagelayout-login .login-layout-left-content {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}
body.pagelayout-login .login-layout-left-content .card {
    max-width: 460px;
    width: 100%;
}
CSS;
    set_config('customcss', $customcss, 'theme_boost');
    set_config('scss', $customcss, 'theme_moove');
    set_config('theme', 'moove');

    // The shared demo accounts cannot change their password, profile or private files.
    $userrole = get_archetype_roles('user');
    $userrole = reset($userrole);
    $system = context_system::instance();
    foreach (['moodle/user:changeownpassword', 'moodle/user:editownprofile', 'moodle/user:manageownfiles'] as $cap) {
        assign_capability($cap, CAP_PROHIBIT, $userrole->id, $system->id, true);
    }
    echo "settings and custom CSS done\n";
}

/**
 * Ensures a comprehensive CodeLab Runner & Admin Guide page exists in the course.
 */
function demo_ensure_admin_guide(int $courseid, string $lang): void {
    global $DB, $CFG;

    $title = ($lang === 'en')
        ? 'CodeLab Runner & Admin Guide: Interactive Execution'
        : 'Vodič za admine i nastavnike: CodeLab Runner';

    $content = ($lang === 'en')
        ? demo_guide_html_en()
        : demo_guide_html_sr();

    $page = $DB->get_record_select('page', "course = ? AND (name LIKE ? OR name LIKE ?)", [$courseid, '%Guide%', '%Vodič%']);
    if ($page) {
        $page->name = $title;
        $page->content = $content;
        $page->timemodified = time();
        $DB->update_record('page', $page);
        echo "Updated Admin Guide page in course {$courseid} ({$lang})\n";
    }
}

/**
 * Detailed HTML guide for CodeLab Runner in English.
 */
function demo_guide_html_en(): string {
    return <<<'HTML'
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
    <div class="d-flex align-items-center mb-3">
        <span class="fs-2 me-3">🚀</span>
        <div>
            <h3 class="fw-bold mb-0 text-primary">CodeLab Runner &amp; Admin Architecture Guide</h3>
            <p class="text-muted mb-0">Interactive multi-language code execution, sandbox isolation, and Moodle integration</p>
        </div>
    </div>
    <hr>
    
    <h4 class="fw-bold mt-3 mb-2">1. System Overview</h4>
    <p>CodeLab for Moodle provides students with runnable code in <strong>C, C++, Python, Java, C#, JavaScript, Pascal, PHP and SQL</strong> directly inside lesson pages, forum posts, and quiz questions.</p>
    <ul>
        <li><strong>Python &amp; JavaScript:</strong> Execute instantly in the browser client-side using WebAssembly (Pyodide). Zero server load and instant response.</li>
        <li><strong>Compiled Languages (C, C++, Java, C#) &amp; SQL:</strong> Run interactively on the <strong>CodeLab Runner</strong> server over secure WebSockets (<code>wss://runner.tsp.edu.rs</code>) with live terminal stdin and step-by-step GDB debugging.</li>
        <li><strong>Automated Quiz Grading:</strong> Uses the backend <strong>Jobe server</strong> (<code>https://jobe.tsp.edu.rs</code>) or the Runner's <code>POST /run</code> HMAC API to verify programs against test suites.</li>
    </ul>

    <h4 class="fw-bold mt-4 mb-2">2. Architecture &amp; Security Isolation</h4>
    <div class="p-3 bg-light rounded-3 mb-3 border">
        <ol class="mb-0">
            <li><strong>HMAC Authentication:</strong> When a student clicks <em>Run</em>, Moodle generates a one-time token signed with <code>CODELAB_SECRET</code> (valid for 60 seconds).</li>
            <li><strong>WebSocket Streaming:</strong> The browser opens <code>wss://&lt;runner&gt;/session?token=...</code> to stream source code and standard input/output interactively.</li>
            <li><strong>Bubblewrap Sandbox (<code>bwrap</code>):</strong> The program executes inside an unprivileged Linux namespace:
                <ul>
                    <li>No network access (<code>--unshare-net</code>).</li>
                    <li>Read-only filesystem mount.</li>
                    <li>Resource limits enforced via <code>prlimit</code> (maximum CPU time, 128 MB RAM, file size cap).</li>
                </ul>
            </li>
            <li><strong>SQL Sandbox (MariaDB):</strong> Each SQL run provisions an ephemeral database and user (<code>cl_...</code>) with strict timeout limits (<code>MAX_STATEMENT_TIME=5s</code>) and cleans up immediately upon session completion.</li>
        </ol>
    </div>

    <h4 class="fw-bold mt-4 mb-2">3. Server Setup for Administrators</h4>
    <p>The runner is a lightweight Node.js service designed to run on Ubuntu 22.04/24.04 LTS:</p>
    <pre class="bg-dark text-light p-3 rounded-3" style="font-size: 13px;"><code># 1. Install prerequisites
sudo apt-get install bubblewrap build-essential openjdk-21-jdk-headless mono-mcs gdb mariadb-server

# 2. Configure systemd service &amp; environment
sudo cp deploy/codelab-runner.service /etc/systemd/system/
sudo cp deploy/codelab-runner.env.example /etc/codelab-runner.env
# Edit /etc/codelab-runner.env: set CODELAB_SECRET, CODELAB_ORIGINS, MariaDB password

# 3. Reload and start
sudo systemctl daemon-reload &amp;&amp; sudo systemctl enable --now codelab-runner</code></pre>

    <h4 class="fw-bold mt-4 mb-2">4. Moodle Plugin Configuration</h4>
    <p>Navigate to <em>Site administration &gt; Plugins &gt; Text editors &gt; TinyMCE editor &gt; CodeLab</em>:</p>
    <ul>
        <li><strong>Address of the interactive server:</strong> <code>wss://runner.tsp.edu.rs</code></li>
        <li><strong>Secret of the interactive server:</strong> Matching <code>CODELAB_SECRET</code></li>
        <li><strong>Address of the Jobe server:</strong> <code>https://jobe.tsp.edu.rs</code></li>
        <li><strong>Python in the browser:</strong> Checked (Enabled)</li>
    </ul>

    <h4 class="fw-bold mt-4 mb-2">5. VS Code Themes &amp; Appearance (v1.5.0)</h4>
    <p>CodeLab v1.5.0 introduces built-in VS Code themes and student toggles:</p>
    <ul>
        <li><strong>Theme Options:</strong>
            <ul>
                <li><code>vs</code> (Visual Studio Light): Clean, high-readability light theme.</li>
                <li><code>vs-dark</code> (Visual Studio Dark): Classic VS Code dark theme for comfortable low-light coding.</li>
                <li><code>hc-black</code> (High Contrast Dark): Maximum accessibility and contrast.</li>
                <li><code>auto</code> (Match Moodle Theme): Dynamically adapts to Moodle's light or dark mode.</li>
            </ul>
        </li>
        <li><strong>Teacher Selection:</strong> When inserting or editing snippets in TinyMCE, pick the desired theme in the modal dialog dropdown. It saves as <code>data-theme="..."</code>.</li>
        <li><strong>Student Dynamic Toggle (🌓):</strong> Students can click the theme toggle button in the CodeLab card toolbar to switch between light, dark, and high-contrast themes on the fly without losing their editor state or code.</li>
        <li><strong>Site Default Setting:</strong> Administrators can choose the site-wide default theme in <em>Site administration &gt; Plugins &gt; Text editors &gt; TinyMCE editor &gt; CodeLab</em>.</li>
    </ul>
</div>
HTML;
}

/**
 * Detailed HTML guide for CodeLab Runner in Serbian.
 */
function demo_guide_html_sr(): string {
    return <<<'HTML'
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
    <div class="d-flex align-items-center mb-3">
        <span class="fs-2 me-3">🚀</span>
        <div>
            <h3 class="fw-bold mb-0 text-success">Vodič za admine i nastavnike: CodeLab Runner</h3>
            <p class="text-muted mb-0">Arhitektura interaktivnog izvršavanja koda, sandbox izolacija i podešavanje Moodle-a</p>
        </div>
    </div>
    <hr>
    
    <h4 class="fw-bold mt-3 mb-2">1. Pregled sistema</h4>
    <p>CodeLab za Moodle omogućava učenicima i nastavnicima pokretanje i debagovanje koda u <strong>C, C++, Python, Java, C#, JavaScript, Pascal, PHP i SQL</strong> jezicima direktno na stranicama lekcija i kvizovima.</p>
    <ul>
        <li><strong>Python i JavaScript:</strong> Izvršavaju se lokalno u pregledaču preko WebAssembly tehnologije (Pyodide). Trenutni odziv i nula opterećenja za server.</li>
        <li><strong>C, C++, Java, C# i SQL:</strong> Izvršavaju se u realnom vremenu na <strong>CodeLab Runner</strong> serveru preko WebSocket protokola (<code>wss://runner.tsp.edu.rs</code>), sa unosom sa tastature tokom rada i debagovanjem liniju po liniju (GDB).</li>
        <li><strong>Automatsko ocenjivanje u kvizovima:</strong> Koristi Jobe server (<code>https://jobe.tsp.edu.rs</code>) ili Runner API za proveru tačnosti učeničkog rešenja prema zadatim test primerima.</li>
    </ul>

    <h4 class="fw-bold mt-4 mb-2">2. Bezbednost i sandbox izolacija</h4>
    <div class="p-3 bg-light rounded-3 mb-3 border">
        <ol class="mb-0">
            <li><strong>Autentifikacija tokenom:</strong> Pri pokretanju koda Moodle izdaje jednokratni HMAC-SHA256 token sa rokom važenja od 60 sekundi.</li>
            <li><strong>WebSocket strimovanje:</strong> Pregledač komunicira direktno sa Runner servisom, čime se obezbeđuje interaktivni rad konzole.</li>
            <li><strong>Bubblewrap Sandbox (<code>bwrap</code>):</strong> Program se pokreće u potpuno izolovanom Linux sandboxu:
                <ul>
                    <li>Bez pristupa lokalnoj mreži ili internetu (<code>--unshare-net</code>).</li>
                    <li>Sistem datoteka montiran samo za čitanje (read-only).</li>
                    <li>Limitirani resursi: maksimalno vreme procesora, 128 MB radne memorije i limitirana veličina datoteka.</li>
                </ul>
            </li>
            <li><strong>SQL bezbedna baza (MariaDB):</strong> Svaka SQL sesija automatski dobija svoju privremenu bazu i korisnika sa vremenskim limitom (5 sekundi), koja se automatski briše po završetku rada.</li>
        </ol>
    </div>

    <h4 class="fw-bold mt-4 mb-2">3. Podešavanje za administratore</h4>
    <pre class="bg-dark text-light p-3 rounded-3" style="font-size: 13px;"><code># 1. Instalacija paketa na serveru
sudo apt-get install bubblewrap build-essential openjdk-21-jdk-headless mono-mcs gdb mariadb-server

# 2. Podešavanje servisa i tajnog ključa
sudo cp deploy/codelab-runner.service /etc/systemd/system/
sudo cp deploy/codelab-runner.env.example /etc/codelab-runner.env
# Uredite /etc/codelab-runner.env (CODELAB_SECRET, CODELAB_ORIGINS)

# 3. Pokretanje servisa
sudo systemctl daemon-reload &amp;&amp; sudo systemctl enable --now codelab-runner</code></pre>

    <h4 class="fw-bold mt-4 mb-2">4. Podešavanja u Moodle administraciji</h4>
    <p>Otvorite <em>Administracija sajta &gt; Dodaci &gt; Editori teksta &gt; TinyMCE editor &gt; CodeLab</em>:</p>
    <ul>
        <li><strong>Adresa interaktivnog servera:</strong> <code>wss://runner.tsp.edu.rs</code></li>
        <li><strong>Tajna interaktivnog servera:</strong> Ista šifra kao <code>CODELAB_SECRET</code></li>
        <li><strong>Adresa Jobe servera:</strong> <code>https://jobe.tsp.edu.rs</code></li>
        <li><strong>Python u pregledaču:</strong> Štiklirano (Uključeno)</li>
    </ul>

    <h4 class="fw-bold mt-4 mb-2">5. VS Code teme i prilagođavanje izgleda (v1.5.0)</h4>
    <p>Verzija v1.5.0 donosi izvorne VS Code teme i jednostavno prebacivanje tema za učenike:</p>
    <ul>
        <li><strong>Dostupne teme:</strong>
            <ul>
                <li><code>vs</code> (Visual Studio svetla): Pregledna svetla tema visoke čitljivosti.</li>
                <li><code>vs-dark</code> (Visual Studio tamna): Standardna VS Code tamna tema za ugodan noćni rad.</li>
                <li><code>hc-black</code> (Visoki kontrast): Pristupačna tema sa maksimalnim kontrastom boja.</li>
                <li><code>auto</code> (Prati Moodle temu): Automatski se prilagođava temi sistema ili korisnika.</li>
            </ul>
        </li>
        <li><strong>Izbor nastavnika:</strong> Prilikom unosa koda u TinyMCE editoru, nastavnik u padajućem meniju bira temu. Ona se čuva kao atribut <code>data-theme="..."</code>.</li>
        <li><strong>Prekidač za učenike (🌓):</strong> Učenici mogu u bilo kom trenutku kliknuti na ikonicu <code>🌓</code> na traci CodeLab kartice kako bi trenutno promenili temu između svetle, tamne i visokog kontrasta, bez gubljenja koda ili stanja izvršavanja.</li>
        <li><strong>Podrazumevana tema sajta:</strong> Administrator može podesiti podrazumevanu temu za ceo sajt na <em>Administracija sajta &gt; Dodaci &gt; Editori teksta &gt; TinyMCE editor &gt; CodeLab</em>.</li>
    </ul>
</div>
HTML;
}

/**
 * Serbian language packs (Latin and Cyrillic), when the server can download them.
 */
function demo_languages(): void {
    try {
        $controller = new \tool_langimport\controller();
        $controller->install_languagepacks(['sr_lt', 'sr_cr']);
        echo "language packs: " . implode(' ', $controller->info) . "\n";
    } catch (\Throwable $e) {
        echo "language packs not installed: " . $e->getMessage() . "\n";
    }
}

/**
 * Sets the course summary and attaches the custom card cover image to overviewfiles.
 */
function demo_set_course_overview(int $courseid, string $summary, string $cardfile): void {
    global $DB;

    // 1. Set summary
    if ($summary !== '') {
        $DB->set_field('course', 'summary', $summary, ['id' => $courseid]);
        $DB->set_field('course', 'summaryformat', FORMAT_HTML, ['id' => $courseid]);
    }

    // 2. Attach overview card image
    $candidates = [
        __DIR__ . '/' . $cardfile,
        __DIR__ . '/' . basename($cardfile),
    ];
    $found = null;
    foreach ($candidates as $cand) {
        if (file_exists($cand)) {
            $found = $cand;
            break;
        }
    }

    if ($found) {
        try {
            $context = \context_course::instance($courseid);
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'course', 'overviewfiles');
            $filerecord = [
                'contextid' => $context->id,
                'component' => 'course',
                'filearea' => 'overviewfiles',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => basename($found),
            ];
            $fs->create_file_from_pathname($filerecord, $found);
            echo "course {$courseid} overview image set: " . basename($found) . "\n";
        } catch (\Throwable $e) {
            echo "course {$courseid} overview image warning: " . $e->getMessage() . "\n";
        }
    }
}

/**
 * Restore a course from an MBZ archive.
 */
function demo_restore_course(string $shortname, string $fullname, string $mbz): int {
    global $DB;
    $folder = 'codelabdemo' . preg_replace('/[^a-zA-Z0-9]/', '', $shortname) . time();
    $path = make_backup_temp_directory($folder);
    get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($mbz, $path);
    $admin = get_admin();
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if ($course) {
        $rc = new restore_controller(
            $folder,
            $course->id,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $admin->id,
            backup::TARGET_EXISTING_DELETING
        );
        $rc->execute_precheck();
        restore_dbops::delete_course_content($course->id, ['keep_roles_and_enrolments' => 1, 'keep_groups_and_groupings' => 1]);
    } else {
        $category = $DB->get_field('course_categories', 'id', [], IGNORE_MULTIPLE);
        $courseid = restore_dbops::create_new_course($fullname, $shortname, $category);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $rc = new restore_controller(
            $folder,
            $course->id,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $admin->id,
            backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
    }
    $rc->execute_plan();
    $rc->destroy();
    $DB->set_field('course', 'shortname', $shortname, ['id' => $course->id]);
    $DB->set_field('course', 'fullname', $fullname, ['id' => $course->id]);
    $DB->set_field('course', 'visible', 1, ['id' => $course->id]);
    demo_guest_access($course->id);
    demo_teacher_limits($course->id);
    rebuild_course_cache($course->id, true);
    echo "course {$shortname} (id {$course->id}) restored\n";
    return (int) $course->id;
}

/**
 * Guests may look at the course and run Python in their browser without an account.
 */
function demo_guest_access(int $courseid): void {
    global $DB;
    $guest = enrol_get_plugin('guest');
    $course = get_course($courseid);
    $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'guest']);
    if (!$instance) {
        $guest->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
    } else if ($instance->status != ENROL_INSTANCE_ENABLED) {
        $guest->update_status($instance, ENROL_INSTANCE_ENABLED);
    }
}

/**
 * The demo teacher edits activities and questions, but not the course itself, its participants or its backups.
 */
function demo_teacher_limits(int $courseid): void {
    global $DB;
    $role = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
    $context = context_course::instance($courseid);
    foreach (['moodle/course:update', 'moodle/course:enrolconfig', 'moodle/course:enrolreview', 'moodle/role:assign',
            'enrol/manual:enrol', 'enrol/manual:manage', 'enrol/guest:config', 'moodle/backup:backupcourse',
            'moodle/restore:restorecourse', 'moodle/course:reset', 'moodle/course:delete'] as $cap) {
        if (get_capability_info($cap)) {
            assign_capability($cap, CAP_PROHIBIT, $role->id, $context->id, true);
        }
    }
    $context->mark_dirty();
}

/**
 * Welcome banner in section 0 of the demo course with 4-step guided tour, guide button, and language toggle.
 */
function demo_section_banner(int $courseid, int $other_courseid, string $lang): void {
    global $CFG, $DB;
    $other_url = "{$CFG->wwwroot}/course/view.php?id={$other_courseid}";

    // Locate the Guide page in this course
    $guide_btn = '';
    $guide_page = $DB->get_record_select('page', "course = ? AND (name LIKE ? OR name LIKE ?)", [$courseid, '%Guide%', '%Vodič%']);
    if ($guide_page) {
        $guide_cm = get_coursemodule_from_instance('page', $guide_page->id, $courseid);
        if ($guide_cm) {
            $guide_url = "{$CFG->wwwroot}/mod/page/view.php?id={$guide_cm->id}";
            $btn_text = ($lang === 'en') ? '📖 Admin &amp; Teacher Guide: CodeLab Runner →' : '📖 Vodič za admine i nastavnike: CodeLab Runner →';
            $guide_btn = '<a href="' . $guide_url . '" class="btn btn-sm btn-warning text-dark rounded-pill px-3 fw-bold shadow-sm" style="backdrop-filter: blur(4px);">' . $btn_text . '</a>';
        }
    }

    if ($lang === 'en') {
        $banner = '<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #2563eb 100%); color: #ffffff;">'
            . '<div class="card-body p-4">'
            . '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">'
            . '<div class="d-flex flex-wrap align-items-center gap-2">'
            . '<span class="badge bg-white bg-opacity-25 text-white px-3 py-2 rounded-pill fw-normal" style="backdrop-filter: blur(4px);">✨ CodeLab Demo · English</span>'
            . $guide_btn
            . '</div>'
            . '<a href="' . $other_url . '" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold" style="backdrop-filter: blur(4px);">🇷🇸 Prebaci na srpski kurs →</a>'
            . '</div>'
            . '<h2 class="fw-bold text-white mb-2">Welcome to CodeLab for Moodle!</h2>'
            . '<p class="mb-3" style="color: rgba(255,255,255,0.9) !important; font-size: 1.05rem;">Experience runnable code in Moodle powered by the Monaco editor (VS Code). Follow this 4-step tour to test all features:</p>'
            . '<div class="row g-2 mb-3">'
            . '<div class="col-md-3 col-6"><div class="p-2 px-3 rounded-3 bg-black bg-opacity-30 h-100"><div class="fw-bold text-warning small mb-1">1. READ &amp; RUN</div><div class="small opacity-90">Open any lesson, edit code &amp; click Run</div></div></div>'
            . '<div class="col-md-3 col-6"><div class="p-2 px-3 rounded-3 bg-black bg-opacity-30 h-100"><div class="fw-bold text-info small mb-1">2. DEBUGGER</div><div class="small opacity-90">Step line-by-line in C &amp; Python with watches</div></div></div>'
            . '<div class="col-md-3 col-6"><div class="p-2 px-3 rounded-3 bg-black bg-opacity-30 h-100"><div class="fw-bold text-success small mb-1">3. SQL SANDBOX</div><div class="small opacity-90">Run live MySQL queries on library tables</div></div></div>'
            . '<div class="col-md-3 col-6"><div class="p-2 px-3 rounded-3 bg-black bg-opacity-30 h-100"><div class="fw-bold text-light small mb-1">4. QUIZZES</div><div class="small opacity-90">Take auto-graded programming tests</div></div></div>'
            . '</div>'
            . '<div class="d-flex flex-wrap align-items-center justify-content-between pt-2 border-top border-white border-opacity-25 small opacity-90">'
            . '<div><strong>Demo Accounts:</strong> <code>student</code> or <code>teacher</code> (password: <code>Demo-2026</code>) · or explore as Guest</div>'
            . '<div>Resets nightly at 03:30</div>'
            . '</div></div></div>';
    } else {
        $banner = '<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #2563eb 100%); color: #ffffff;">'
            . '<div class="card-body p-4">'
            . '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">'
            . '<div class="d-flex flex-wrap align-items-center gap-2">'
            . '<span class="badge bg-white bg-opacity-25 text-white px-3 py-2 rounded-pill fw-normal" style="backdrop-filter: blur(4px);">✨ CodeLab Demo · Srpski</span>'
            . $guide_btn
            . '</div>'
            . '<a href="' . $other_url . '" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold" style="backdrop-filter: blur(4px);">🌐 Switch to English Course →</a>'
            . '</div>'
            . '<h2 class="fw-bold text-white mb-2">Dobrodošli u CodeLab za Moodle!</h2>'
            . '<p class="mb-3" style="color: rgba(255,255,255,0.9) !important; font-size: 1.05rem;">Pokrećite i menjajte kod u lekcijama u VS Code Monaco editoru. Isprobajte sve funkcionalnosti u 4 koraka:</p>'
            . '<div class="row g-2 mb-3">'
            . '<div class="col-md-3 col-6"><div class="p-2 px-3 rounded-3 bg-black bg-opacity-30 h-100"><div class="fw-bold text-warning small mb-1">1. LEKCIJE I PRIMERI</div><div class="small opacity-90">Pokrenite programe u C, C#, Python i drugim jezicima</div></div></div>'
            . '<div class="col-md-3 col-6"><div class="p-2 px-3 rounded-3 bg-black bg-opacity-30 h-100"><div class="fw-bold text-info small mb-1">2. DEBAGER</div><div class="small opacity-90">Koračajte red po red kroz C i Python uz praćenje promenljivih</div></div></div>'
            . '<div class="col-md-3 col-6"><div class="p-2 px-3 rounded-3 bg-black bg-opacity-30 h-100"><div class="fw-bold text-success small mb-1">3. SQL BIBLIOTEKA</div><div class="small opacity-90">Izvršavajte MySQL upite u pravoj bazi podataka</div></div></div>'
            . '<div class="col-md-3 col-6"><div class="p-2 px-3 rounded-3 bg-black bg-opacity-30 h-100"><div class="fw-bold text-light small mb-1">4. KVIZOVI</div><div class="small opacity-90">Rešavajte zadatke sa automatskim testovima i bodovanjem</div></div></div>'
            . '</div>'
            . '<div class="d-flex flex-wrap align-items-center justify-content-between pt-2 border-top border-white border-opacity-25 small opacity-90">'
            . '<div><strong>Demo nalozi:</strong> <code>student</code> ili <code>teacher</code> (lozinka: <code>Demo-2026</code>) · ili pristupite kao Gost</div>'
            . '<div>Resetuje se svake noći u 03:30</div>'
            . '</div></div></div>';
    }
    $DB->set_field('course_sections', 'summary', $banner, ['course' => $courseid, 'section' => 0]);
    $DB->set_field('course_sections', 'summaryformat', FORMAT_HTML, ['course' => $courseid, 'section' => 0]);
}

/**
 * Fallback section banner when only one course exists.
 */
function demo_section_banner_single(int $courseid): void {
    global $DB;
    $banner = '<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #2563eb 100%); color: #ffffff;">'
        . '<div class="card-body p-4">'
        . '<h2 class="fw-bold text-white mb-2">Welcome to CodeLab for Moodle!</h2>'
        . '<p class="mb-3 text-white-50">Edit and run programs in the VS Code editor, follow C and Python with a debugger, run SQL, and test automatic grading.</p>'
        . '</div></div>';
    $DB->set_field('course_sections', 'summary', $banner, ['course' => $courseid, 'section' => 0]);
    $DB->set_field('course_sections', 'summaryformat', FORMAT_HTML, ['course' => $courseid, 'section' => 0]);
}

/**
 * The shared demo accounts enrolled in the demo course.
 */
function demo_users(int $courseid): void {
    global $CFG, $DB;
    $manual = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual']);
    if (!$instance) {
        $manual->add_instance(get_course($courseid));
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
    }
    foreach (DEMO_USERS as $username => $u) {
        $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]);
        if (!$user) {
            $id = user_create_user((object) [
                'username' => $username,
                'password' => DEMO_PASSWORD,
                'auth' => 'manual',
                'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
                'firstname' => $u['firstname'],
                'lastname' => $u['lastname'],
                'email' => "{$username}@codelab.tsp.edu.rs",
                'lang' => 'en',
            ], true, false);
            $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
        } else {
            update_internal_user_password($user, DEMO_PASSWORD);
        }
        $roleid = $DB->get_field('role', 'id', ['shortname' => $u['role']], MUST_EXIST);
        $manual->enrol_user($instance, $user->id, $roleid);
        echo "user {$username} enrolled in course {$courseid} as {$u['role']}\n";
    }
}

/**
 * Front page with live runnable code snippet, dual-language course cards, and quick login actions.
 */
function demo_frontpage(int $en_id, int $sr_id): void {
    global $CFG, $DB;
    $en_url = "{$CFG->wwwroot}/course/view.php?id={$en_id}";
    $sr_url = "{$CFG->wwwroot}/course/view.php?id={$sr_id}";

    // Locate guides
    $en_guide_url = "{$CFG->wwwroot}/course/view.php?id={$en_id}";
    $sr_guide_url = "{$CFG->wwwroot}/course/view.php?id={$sr_id}";
    $g_en = $DB->get_record_select('page', "course = ? AND name LIKE ?", [$en_id, '%Guide%']);
    if ($g_en) {
        $cm = get_coursemodule_from_instance('page', $g_en->id, $en_id);
        if ($cm) {
            $en_guide_url = "{$CFG->wwwroot}/mod/page/view.php?id={$cm->id}";
        }
    }
    $g_sr = $DB->get_record_select('page', "course = ? AND name LIKE ?", [$sr_id, '%Vodič%']);
    if ($g_sr) {
        $cm = get_coursemodule_from_instance('page', $g_sr->id, $sr_id);
        if ($cm) {
            $sr_guide_url = "{$CFG->wwwroot}/mod/page/view.php?id={$cm->id}";
        }
    }

    $summary = '<p>This is the demo site of the <strong>CodeLab plugins for Moodle</strong>: '
        . '<a href="https://github.com/blagojevicboban/moodle-tiny_codelab">CodeLab for TinyMCE</a> (code examples in the editor), '
        . '<a href="https://github.com/blagojevicboban/moodle-filter_codelab">CodeLab filter</a> (run and step for readers) and '
        . '<a href="https://github.com/blagojevicboban/moodle-qtype_codelab">CodeLab question type</a> (programs graded on tests).</p>'
        . '<p>Open the demo courses as a guest (Python runs), or log in with one of these accounts (password <strong>' . DEMO_PASSWORD . '</strong>):</p><ul>'
        . '<li><strong>teacher</strong>: edit the pages and the quiz questions, insert code examples</li>'
        . '<li><strong>student</strong>: run every language, step through C and Python, take the quizzes</li></ul>'
        . '<p>Courses available: <a href="' . $en_url . '"><strong>CodeLab: demo (English)</strong></a> · <a href="' . $sr_url . '"><strong>CodeLab: demo (srpski)</strong></a> · <a href="' . $en_guide_url . '"><strong>📖 Administrator &amp; Teacher Guide</strong></a></p>'
        . '<p>Everything you change is reset every night. Serbian (Latin and Cyrillic) is also in the language menu.</p>';
    $DB->update_record('course', (object) [
        'id' => SITEID,
        'fullname' => 'CodeLab for Moodle: demo',
        'shortname' => 'CodeLab demo',
        'summary' => $summary,
        'summaryformat' => FORMAT_HTML,
    ]);

    $live_code = htmlspecialchars(
        "# Welcome to CodeLab for Moodle!\n"
        . "# Click 'Run' below to execute this code live in your browser:\n"
        . "def demo_preview():\n"
        . "    languages = ['Python', 'C', 'C++', 'Java', 'C#', 'JS', 'Pascal', 'PHP', 'SQL']\n"
        . "    print(f'🚀 CodeLab powers runnable code across {len(languages)} languages!')\n"
        . "    for idx, lang in enumerate(languages, 1):\n"
        . "        print(f'   {idx}. {lang}')\n"
        . "    print('✨ Ready to explore? Pick a demo course below!')\n\n"
        . "demo_preview()\n",
        ENT_QUOTES
    );

    $hero = '<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #2563eb 100%); color: #ffffff;">'
        . '<div class="card-body p-4 p-md-5">'
        . '<div class="row align-items-center">'
        . '<div class="col-lg-7">'
        . '<div class="d-flex flex-wrap gap-2 mb-3">'
        . '<span class="badge bg-white bg-opacity-25 text-white px-3 py-2 rounded-pill fw-normal" style="backdrop-filter: blur(4px);">✨ Official Demo for CodeLab Plugins</span>'
        . '<span class="badge bg-info bg-opacity-25 text-white px-3 py-2 rounded-pill fw-normal">⚡ 8 Languages + SQL</span>'
        . '<span class="badge bg-warning bg-opacity-25 text-white px-3 py-2 rounded-pill fw-normal">🐞 Line-by-Line Debugger</span>'
        . '<span class="badge bg-success bg-opacity-25 text-white px-3 py-2 rounded-pill fw-normal">🎨 VS Code Themes (Light/Dark/HC)</span>'
        . '<a href="' . $en_guide_url . '" class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold text-decoration-none shadow-sm">📖 Admin &amp; Runner Guide →</a>'
        . '</div>'
        . '<h1 class="display-6 fw-bold text-white mb-3">Runnable Code in Moodle with the VS Code Editor</h1>'
        . '<p class="lead mb-4" style="color: rgba(255,255,255,0.92) !important; font-size: 1.15rem;">Write examples in C, C++, Python, Java, C#, JavaScript, Pascal, PHP and SQL. Choose from VS Code themes (Light, Dark, High Contrast), edit and run with live stdin, step through line-by-line with a debugger, and be graded automatically in quizzes.</p>'
        . '<div class="d-flex flex-wrap gap-3 mb-2">'
        . '<a href="' . $en_url . '" class="btn btn-light btn-lg px-4 fw-bold shadow-sm" style="border-radius: 12px; color: #1e3a8a;">🇬🇧 Open English Demo</a>'
        . '<a href="' . $sr_url . '" class="btn btn-outline-light btn-lg px-4 fw-bold shadow-sm" style="border-radius: 12px; border-width: 2px;">🇷🇸 Otvori kurs na srpskom</a>'
        . '<a href="' . $en_guide_url . '" class="btn btn-warning text-dark btn-lg px-4 fw-bold shadow-sm" style="border-radius: 12px;">📖 Admin &amp; Teacher Guide</a>'
        . '</div>'
        . '</div>'
        . '<div class="col-lg-5 mt-4 mt-lg-0">'
        . '<div class="p-3 bg-dark bg-opacity-75 rounded-4 shadow-lg border border-white border-opacity-10">'
        . '<div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom border-white border-opacity-10">'
        . '<div class="small fw-bold text-white"><span class="me-2">▶️</span>Try it live now (no login required):</div>'
        . '<span class="badge bg-success bg-opacity-50 text-white font-monospace small">VS Code Dark Theme</span>'
        . '</div>'
        . '<pre class="codelab codelab-lang-python codelab-run" data-theme="vs-dark" style="margin:0; font-size:13px; max-height:220px;">' . $live_code . '</pre>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '</div></div>'
        // Dual-Language Cards
        . '<div class="row g-3 mb-4">'
        . '<div class="col-md-6">'
        . '<div class="card h-100 border-0 shadow-sm p-4 demo-course-card" style="background:#ffffff; border-left: 5px solid #2563eb !important;">'
        . '<div class="d-flex justify-content-between align-items-start mb-2">'
        . '<h4 class="fw-bold mb-0 text-primary">🇬🇧 CodeLab: Demo (English)</h4>'
        . '<span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill">CODELAB-DEMO</span>'
        . '</div>'
        . '<p class="text-muted mb-3">Explore programming lessons in C, C#, Python and SQL. Includes interactive assignments, the step-by-step factorial debugger, and autograded quizzes.</p>'
        . '<a href="' . $en_url . '" class="btn btn-primary fw-bold px-4 rounded-3 align-self-start">🚀 Enter English Course →</a>'
        . '</div></div>'
        . '<div class="col-md-6">'
        . '<div class="card h-100 border-0 shadow-sm p-4 demo-course-card" style="background:#ffffff; border-left: 5px solid #059669 !important;">'
        . '<div class="d-flex justify-content-between align-items-start mb-2">'
        . '<h4 class="fw-bold mb-0 text-success">🇷🇸 CodeLab: Demo (srpski)</h4>'
        . '<span class="badge bg-success bg-opacity-10 text-success px-3 py-1 rounded-pill">CODELAB-DEMO-SR</span>'
        . '</div>'
        . '<p class="text-muted mb-3">Kompletne lekcije i zadaci iz C i C# jezika, MySQL biblioteka baza, debager za C i Python red po red, i automatski ocenjivani kvizovi sa testovima.</p>'
        . '<a href="' . $sr_url . '" class="btn btn-success fw-bold px-4 rounded-3 align-self-start">🇷🇸 Otvori kurs na srpskom →</a>'
        . '</div></div>'
        . '</div>'
        // Test Accounts Strip
        . '<div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-light">'
        . '<div class="row g-2 align-items-center text-center text-md-start">'
        . '<div class="col-md-3 fw-bold text-dark"><span class="fs-5 me-2">⚡</span>Instant Demo Access:</div>'
        . '<div class="col-md-3"><div class="p-2 px-3 bg-white rounded-3 shadow-sm border small"><strong>👤 Guest:</strong> Explore &amp; run Python in browser</div></div>'
        . '<div class="col-md-3"><div class="p-2 px-3 bg-white rounded-3 shadow-sm border small"><strong>🎓 Student:</strong> <code>student</code> / <code>Demo-2026</code></div></div>'
        . '<div class="col-md-3"><div class="p-2 px-3 bg-white rounded-3 shadow-sm border small"><strong>🧑‍🏫 Teacher:</strong> <code>teacher</code> / <code>Demo-2026</code></div></div>'
        . '</div></div>'
        // 3 Plugin Cards
        . '<div class="row g-3 mb-4">'
        . '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm rounded-3 p-3"><div class="d-flex align-items-center mb-2"><span class="fs-4 me-2">⌨️</span><h5 class="card-title mb-0 fw-bold"><a href="https://github.com/blagojevicboban/moodle-tiny_codelab" target="_blank" class="text-decoration-none">tiny_codelab</a></h5></div><p class="card-text text-muted small mb-0">Insert runnable examples in any Moodle text with the Monaco editor of VS Code: syntax highlighting, auto-complete, Run on Jobe or Python in browser.</p></div></div>'
        . '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm rounded-3 p-3"><div class="d-flex align-items-center mb-2"><span class="fs-4 me-2">▶️</span><h5 class="card-title mb-0 fw-bold"><a href="https://github.com/blagojevicboban/moodle-filter_codelab" target="_blank" class="text-decoration-none">filter_codelab</a></h5></div><p class="card-text text-muted small mb-0">Turns examples into interactive editors readers can run: live stdin input, Step debugger with breakpoints, call stack and trace table, SQL queries.</p></div></div>'
        . '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm rounded-3 p-3"><div class="d-flex align-items-center mb-2"><span class="fs-4 me-2">📋</span><h5 class="card-title mb-0 fw-bold"><a href="https://github.com/blagojevicboban/moodle-qtype_codelab" target="_blank" class="text-decoration-none">qtype_codelab</a></h5></div><p class="card-text text-muted small mb-0">Programming questions graded automatically against test cases: test weights, hidden tests, number and regex comparisons, penalties, SQL evaluation.</p></div></div>'
        . '</div>';

    $DB->set_field('course_sections', 'summary', $hero, ['course' => SITEID, 'section' => 1]);
    $DB->set_field('course_sections', 'summaryformat', FORMAT_HTML, ['course' => SITEID, 'section' => 1]);
    set_config('numsections', 1);
    set_config('frontpage', FRONTPAGEALLCOURSELIST);
    set_config('frontpageloggedin', FRONTPAGEALLCOURSELIST);
    echo "front page done\n";
}

/**
 * Front page with a single course fallback.
 */
function demo_frontpage_single(int $courseid): void {
    global $CFG, $DB;
    $url = "{$CFG->wwwroot}/course/view.php?id={$courseid}";
    $hero = '<div class="card border-0 shadow-sm rounded-4 mb-4 p-5 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);">'
        . '<h1 class="display-6 fw-bold mb-3">CodeLab for Moodle Demo</h1>'
        . '<a href="' . $url . '" class="btn btn-light btn-lg px-4 fw-bold">Open Course</a>'
        . '</div>';
    $DB->set_field('course_sections', 'summary', $hero, ['course' => SITEID, 'section' => 1]);
    $DB->set_field('course_sections', 'summaryformat', FORMAT_HTML, ['course' => SITEID, 'section' => 1]);
}
