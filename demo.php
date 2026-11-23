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
    // The video on the front page is a plain YouTube link that the media filter turns into a player.
    filter_set_global_state('mediaplugin', TEXTFILTER_ON);
    \core\plugininfo\media::enable_plugin('youtube', 1);
    if (file_exists($CFG->dirroot . '/filter/algorithm/version.php')) {
        filter_set_global_state('algorithm', TEXTFILTER_ON);
    }
    if (file_exists($CFG->dirroot . '/filter/aisim/version.php')) {
        filter_set_global_state('aisim', TEXTFILTER_ON);
    }
    set_config('jobeserver', 'https://jobe.tsp.edu.rs', 'tiny_codelab');
    set_config('runnerurl', 'wss://runner.tsp.edu.rs', 'tiny_codelab');
    set_config('backend', 'runner', 'tiny_codelab');
    set_config('browserpython', 1, 'tiny_codelab');
    if (class_exists('\tiny_codelab\local\languages')) {
        set_config('languages', implode(',', \tiny_codelab\local\languages::all()), 'tiny_codelab');
    }

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
        
        <h5 class="fw-bold mb-0 text-white">Instant Demo Access</h5>
    </div>
    <div class="p-3 rounded-3 mb-2" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.12);">
        <div class="fw-bold mb-1" style="color: #38bdf8;">Teacher: <code>teacher</code></div>
        <div class="small" style="color: rgba(255, 255, 255, 0.9); line-height: 1.4;">Insert &amp; edit runnable Monaco code in TinyMCE (27 languages), configure test cases, unit tests and auto-grading.</div>
    </div>
    <div class="p-3 rounded-3 mb-3" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.12);">
        <div class="fw-bold mb-1" style="color: #fbbf24;">Student: <code>student</code></div>
        <div class="small" style="color: rgba(255, 255, 255, 0.9); line-height: 1.4;">Run 27 languages (including HTML/CSS/JS &amp; SQL) with live stdin, step through C &amp; Python with debugger, solve coding quizzes.</div>
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

    // Allow guests on the public demo to run programs (C, C++, Java, Python...)
    $guestroles = get_archetype_roles('guest');
    if (!empty($guestroles)) {
        $guestrole = reset($guestroles);
        assign_capability('tiny/codelab:run', CAP_ALLOW, $guestrole->id, $system->id, true);
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
    <div class="mb-3">
        <h3 class="fw-bold mb-0 text-primary">CodeLab Runner &amp; Admin Guide</h3>
        <p class="text-muted mb-0">Where programs run, how they are isolated, and how to set up the servers and Moodle</p>
    </div>
    <hr>

    <h4 class="fw-bold mt-3 mb-2">1. Where programs run</h4>
    <p>CodeLab examples and questions support <strong>27 languages</strong>. Each one runs in one of three places:</p>
    <ul>
        <li><strong>In the browser:</strong> Python (Pyodide, with <code>input()</code>, Step and turtle graphics) and HTML / CSS / JS (a sandboxed preview frame with a console). No server load; this also works for guests.</li>
        <li><strong>On the CodeLab runner:</strong> C, C++, Java, C#, Python, JavaScript, TypeScript, Pascal, PHP, Bash, Go, Rust, R, Ruby, Lua, Kotlin, Perl, Octave, Julia, Scheme, Dart, Scala, Elixir, Swift, Haskell and SQL (MariaDB). Runs are interactive: the program asks for input while it runs, as in a terminal. C and C++ also have Step (the GDB debugger, Memory view) and Safe mode (AddressSanitizer and UndefinedBehaviorSanitizer).</li>
        <li><strong>On Jobe:</strong> C, C++, Java, C#, Python, JavaScript, Pascal and PHP, with the whole input given before the run.</li>
    </ul>
    <p><strong>Grading</strong> of quiz questions is always done on a server, never in the browser: on Jobe, or on the runner through its signed <code>POST /run</code> API. The runner grades like Jobe (the same statuses for compile errors, runtime errors and time limits), so a question gives the same grade on both.</p>

    <h4 class="fw-bold mt-4 mb-2">2. How a run is protected</h4>
    <div class="p-3 bg-light rounded-3 mb-3 border">
        <ol class="mb-0">
            <li><strong>Signed token:</strong> when a reader clicks <em>Run</em>, Moodle signs a short-lived token with the shared secret (<code>CODELAB_SECRET</code>). It carries only the numeric user id.</li>
            <li><strong>WebSocket session:</strong> the browser opens <code>wss://&lt;runner&gt;/session</code> with the token and streams the code, the input and the output.</li>
            <li><strong>Bubblewrap sandbox (<code>bwrap</code>):</strong> every compilation and run is in its own Linux namespace:
                <ul>
                    <li>no network;</li>
                    <li>only <code>/usr</code> and <code>/etc</code> of the server, read-only, so compilers must be installed under <code>/usr</code>;</li>
                    <li>a private working folder and a small temporary folder;</li>
                    <li>limits with <code>prlimit</code>: CPU time, memory (the JVM, Mono and Node.js get a heap limit instead), file size and output size.</li>
                </ul>
            </li>
            <li><strong>SQL:</strong> each run gets its own temporary MariaDB database and user (<code>cl_...</code>), with a 5 second limit per statement; both are dropped at the end.</li>
            <li><strong>Moodle side:</strong> a rate limit per user and a size limit for code and input protect the servers.</li>
        </ol>
    </div>

    <h4 class="fw-bold mt-4 mb-2">3. Setting up your own runner</h4>
    <p>The runner (open source, <a href="https://github.com/blagojevicboban/codelab-runner">github.com/blagojevicboban/codelab-runner</a>) is a small Node.js service for Ubuntu 24.04:</p>
    <pre class="bg-dark text-light p-3 rounded-3" style="font-size: 13px;"><code># 1. The base: sandbox, C/C++, Java, C#, debugger, SQL, Python, Node.js, Pascal, PHP
sudo apt-get install bubblewrap build-essential gdb openjdk-21-jdk-headless mono-mcs mariadb-server \
    python3 nodejs fp-compiler php-cli

# 2. Optional languages from Ubuntu (each is offered only when installed)
sudo apt-get install golang-go rustc r-base-core ruby lua5.4 perl octave guile-3.0 elixir ghc kotlin scala
# Julia, Dart and Swift come from their own downloads, installed under /usr

# 3. Service and settings
sudo cp deploy/codelab-runner.service /etc/systemd/system/
sudo cp deploy/codelab-runner.env.example /etc/codelab-runner.env
# Edit /etc/codelab-runner.env: CODELAB_SECRET, CODELAB_ORIGINS, MariaDB password
sudo systemctl daemon-reload &amp;&amp; sudo systemctl enable --now codelab-runner

# 4. Check: the languages the server offers
curl -s http://127.0.0.1:8090/health</code></pre>

    <h4 class="fw-bold mt-4 mb-2">4. Moodle settings</h4>
    <p><em>Site administration &gt; Plugins &gt; Text editors &gt; TinyMCE editor &gt; CodeLab</em>:</p>
    <ul>
        <li><strong>Server for running and grading:</strong> Automatic (Jobe when it is set, otherwise the runner), Jobe, or CodeLab runner (no Jobe needed). Interactive runs, Step and SQL always use the runner.</li>
        <li><strong>Address of the interactive server</strong> (<code>wss://runner.example.org</code>) and <strong>Secret of the interactive server</strong> (the same as <code>CODELAB_SECRET</code>).</li>
        <li><strong>Jobe server:</strong> for example <code>https://jobe.example.org</code>, or empty to use only the runner.</li>
        <li><strong>Connection key</strong> (optional): instead of your own servers, a hosted CodeLab runner with all languages. It is a paid plan with a monthly quota of runs, provided by the author of CodeLab (ask at boban.blagojevic@tsp.edu.rs).</li>
        <li><strong>Python in the browser:</strong> on (Chrome or Edge 137+; other browsers run Python on the server).</li>
        <li><strong>Languages:</strong> the languages teachers can choose; only those installed on the server run there.</li>
    </ul>
    <p>The settings page tests Jobe and the runner, shows their versions and measures their speed.</p>

    <h4 class="fw-bold mt-4 mb-2">5. The editor for readers</h4>
    <ul>
        <li><strong>Themes:</strong> Visual Studio Light, Visual Studio Dark, High Contrast Dark, or Automatic (follows the site and the system). The administrator sets the default, the teacher can choose one per example, and readers switch with the theme button.</li>
        <li><strong>Interface in 29 languages</strong>, screen reader and keyboard friendly console, zoom with Ctrl and the mouse wheel, full screen, and input from a phone keyboard.</li>
        <li><strong>Help:</strong> typing help and program templates for every language, tooltips for beginners, explanations of 40 common compiler errors, and optional AI explanations through Moodle's AI subsystem.</li>
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
    <div class="mb-3">
        <h3 class="fw-bold mb-0 text-success">Vodič za admine i nastavnike: CodeLab Runner</h3>
        <p class="text-muted mb-0">Gde se programi izvršavaju, kako su izolovani i kako se podešavaju serveri i Moodle</p>
    </div>
    <hr>

    <h4 class="fw-bold mt-3 mb-2">1. Gde se programi izvršavaju</h4>
    <p>CodeLab primeri i pitanja podržavaju <strong>27 jezika</strong>. Svaki se izvršava na jednom od tri mesta:</p>
    <ul>
        <li><strong>U pregledaču:</strong> Python (Pyodide, sa <code>input()</code>, korak po korak i kornjača grafikom) i HTML / CSS / JS (izolovan prikaz stranice sa konzolom). Server nije opterećen, a radi i za goste.</li>
        <li><strong>Na CodeLab runneru:</strong> C, C++, Java, C#, Python, JavaScript, TypeScript, Pascal, PHP, Bash, Go, Rust, R, Ruby, Lua, Kotlin, Perl, Octave, Julia, Scheme, Dart, Scala, Elixir, Swift, Haskell i SQL (MariaDB). Pokretanje je interaktivno: program traži ulaz dok radi, kao u terminalu. C i C++ imaju i izvršavanje korak po korak (GDB debager, prikaz memorije) i Bezbedan režim (AddressSanitizer i UndefinedBehaviorSanitizer).</li>
        <li><strong>Na Jobe-u:</strong> C, C++, Java, C#, Python, JavaScript, Pascal i PHP, sa celim ulazom zadatim pre pokretanja.</li>
    </ul>
    <p><strong>Ocenjivanje</strong> pitanja u kvizu je uvek na serveru, nikad u pregledaču: na Jobe-u ili na runneru preko potpisanog <code>POST /run</code> API-ja. Runner ocenjuje kao Jobe (isti statusi za greške kompajliranja, greške izvršavanja i prekoračeno vreme), pa pitanje dobija istu ocenu na oba.</p>

    <h4 class="fw-bold mt-4 mb-2">2. Kako je pokretanje zaštićeno</h4>
    <div class="p-3 bg-light rounded-3 mb-3 border">
        <ol class="mb-0">
            <li><strong>Potpisan token:</strong> kad čitalac klikne <em>Pokreni</em>, Moodle zajedničkom tajnom (<code>CODELAB_SECRET</code>) potpiše kratkotrajni token. On nosi samo brojčani ID korisnika.</li>
            <li><strong>WebSocket sesija:</strong> pregledač sa tokenom otvara <code>wss://&lt;runner&gt;/session</code> i kroz nju šalje kod i ulaz, a prima izlaz.</li>
            <li><strong>Bubblewrap sandbox (<code>bwrap</code>):</strong> svako kompajliranje i pokretanje je u svom Linux namespace-u:
                <ul>
                    <li>bez mreže;</li>
                    <li>samo <code>/usr</code> i <code>/etc</code> servera, samo za čitanje, pa kompajleri moraju biti instalirani u <code>/usr</code>;</li>
                    <li>privatni radni folder i mali privremeni folder;</li>
                    <li>ograničenja preko <code>prlimit</code>: procesorsko vreme, memorija (JVM, Mono i Node.js umesto toga dobijaju ograničen heap), veličina fajlova i izlaza.</li>
                </ul>
            </li>
            <li><strong>SQL:</strong> svako pokretanje dobija svoju privremenu MariaDB bazu i korisnika (<code>cl_...</code>), sa ograničenjem od 5 sekundi po naredbi; oboje se brišu na kraju.</li>
            <li><strong>Na strani Moodle-a:</strong> ograničenje broja pokretanja po korisniku i veličine koda i ulaza štiti servere.</li>
        </ol>
    </div>

    <h4 class="fw-bold mt-4 mb-2">3. Sopstveni runner</h4>
    <p>Runner (otvoren kod, <a href="https://github.com/blagojevicboban/codelab-runner">github.com/blagojevicboban/codelab-runner</a>) je mali Node.js servis za Ubuntu 24.04:</p>
    <pre class="bg-dark text-light p-3 rounded-3" style="font-size: 13px;"><code># 1. Osnova: sandbox, C/C++, Java, C#, debager, SQL, Python, Node.js, Pascal, PHP
sudo apt-get install bubblewrap build-essential gdb openjdk-21-jdk-headless mono-mcs mariadb-server \
    python3 nodejs fp-compiler php-cli

# 2. Dodatni jezici iz Ubuntu-a (svaki se nudi samo kad je instaliran)
sudo apt-get install golang-go rustc r-base-core ruby lua5.4 perl octave guile-3.0 elixir ghc kotlin scala
# Julia, Dart i Swift se preuzimaju sa svojih sajtova i instaliraju u /usr

# 3. Servis i podešavanja
sudo cp deploy/codelab-runner.service /etc/systemd/system/
sudo cp deploy/codelab-runner.env.example /etc/codelab-runner.env
# Uredite /etc/codelab-runner.env: CODELAB_SECRET, CODELAB_ORIGINS, lozinka za MariaDB
sudo systemctl daemon-reload &amp;&amp; sudo systemctl enable --now codelab-runner

# 4. Provera: jezici koje server nudi
curl -s http://127.0.0.1:8090/health</code></pre>

    <h4 class="fw-bold mt-4 mb-2">4. Podešavanja u Moodle-u</h4>
    <p><em>Administracija sajta &gt; Dodaci &gt; Editori teksta &gt; TinyMCE editor &gt; CodeLab</em>:</p>
    <ul>
        <li><strong>Server za izvršavanje i ocenjivanje:</strong> Automatski (Jobe kad je podešen, inače runner), Jobe ili CodeLab runner (bez Jobe-a). Interaktivno pokretanje, korak po korak i SQL uvek koriste runner.</li>
        <li><strong>Adresa interaktivnog servera</strong> (<code>wss://runner.example.org</code>) i <strong>Tajna interaktivnog servera</strong> (ista kao <code>CODELAB_SECRET</code>).</li>
        <li><strong>Jobe server:</strong> na primer <code>https://jobe.example.org</code>, ili prazno ako se koristi samo runner.</li>
        <li><strong>Ključ za povezivanje</strong> (nije obavezan): umesto sopstvenih servera, hostovani CodeLab runner sa svim jezicima. To je plaćeni plan sa mesečnom kvotom pokretanja, koji daje autor CodeLab-a (ključ se traži na boban.blagojevic@tsp.edu.rs).</li>
        <li><strong>Python u pregledaču:</strong> uključeno (Chrome ili Edge 137+; drugi pregledači izvršavaju Python na serveru).</li>
        <li><strong>Jezici:</strong> jezici koje nastavnici mogu da izaberu; na serveru rade samo oni koji su na njemu instalirani.</li>
    </ul>
    <p>Stranica podešavanja testira Jobe i runner, prikazuje njihove verzije i meri im brzinu.</p>

    <h4 class="fw-bold mt-4 mb-2">5. Editor za čitaoce</h4>
    <ul>
        <li><strong>Teme:</strong> Visual Studio svetla, Visual Studio tamna, tamna visokog kontrasta ili Automatski (prati sajt i sistem). Administrator bira podrazumevanu, nastavnik može da izabere temu za svaki primer, a čitaoci je menjaju dugmetom za temu.</li>
        <li><strong>Interfejs na 29 jezika</strong>, konzola prilagođena čitačima ekrana i tastaturi, uvećanje sa Ctrl i točkićem miša, ceo ekran i unos sa tastature telefona.</li>
        <li><strong>Pomoć:</strong> pomoć pri kucanju i šabloni programa za svaki jezik, objašnjenja za početnike uz miša, objašnjenja 40 čestih grešaka kompajlera i opciona AI objašnjenja preko Moodle AI podsistema.</li>
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

    // Allow guests on the demo course to run programs (C, C++, Java, Python...)
    $guestroles = get_archetype_roles('guest');
    if (!empty($guestroles)) {
        $guestrole = reset($guestroles);
        $context = context_course::instance($courseid);
        assign_capability('tiny/codelab:run', CAP_ALLOW, $guestrole->id, $context->id, true);
        $context->mark_dirty();
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
            $btn_text = ($lang === 'en') ? 'Admin &amp; Teacher Guide: CodeLab Runner →' : 'Vodič za admine i nastavnike: CodeLab Runner →';
            $guide_btn = '<a href="' . $guide_url . '" class="btn btn-sm btn-warning text-dark rounded-pill px-3 fw-bold shadow-sm" style="backdrop-filter: blur(4px);">' . $btn_text . '</a>';
        }
    }

    if ($lang === 'en') {
        $banner = '<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #2563eb 100%); color: #ffffff;">'
            . '<div class="card-body p-4">'
            . '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">'
            . '<div class="d-flex flex-wrap align-items-center gap-2">'
            . '<span class="badge bg-white bg-opacity-25 text-white px-3 py-2 rounded-pill fw-normal" style="backdrop-filter: blur(4px);">CodeLab Demo · English</span>'
            . $guide_btn
            . '</div>'
            . '<a href="' . $other_url . '" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold" style="backdrop-filter: blur(4px);">Prebaci na srpski kurs →</a>'
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
            . '<span class="badge bg-white bg-opacity-25 text-white px-3 py-2 rounded-pill fw-normal" style="backdrop-filter: blur(4px);">CodeLab Demo · Srpski</span>'
            . $guide_btn
            . '</div>'
            . '<a href="' . $other_url . '" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold" style="backdrop-filter: blur(4px);">Switch to English Course →</a>'
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
        . '<a href="https://github.com/blagojevicboban/moodle-qtype_codelab">CodeLab question type</a> (programs graded on tests). Install them from the Moodle Marketplace: <a href="https://marketplace.moodle.com/plugins/tiny_codelab">tiny_codelab</a>, <a href="https://marketplace.moodle.com/plugins/filter_codelab">filter_codelab</a> and <a href="https://marketplace.moodle.com/plugins/qtype_codelab">qtype_codelab</a>.</p>'
        . '<p>Open the demo courses as a guest, or log in with one of these accounts (password <strong>' . DEMO_PASSWORD . '</strong>):</p><ul>'
        . '<li><strong>teacher</strong>: edit the pages and the quiz questions, insert code examples</li>'
        . '<li><strong>student</strong>: run every language, step through C and Python, take the quizzes</li></ul>'
        . '<p>Courses available: <a href="' . $en_url . '"><strong>CodeLab: demo (English)</strong></a> · <a href="' . $sr_url . '"><strong>CodeLab: demo (srpski)</strong></a> · <a href="' . $en_guide_url . '"><strong>Administrator &amp; Teacher Guide</strong></a></p>'
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
        . "    print(f'CodeLab powers runnable code across {len(languages)} languages!')\n"
        . "    for idx, lang in enumerate(languages, 1):\n"
        . "        print(f'   {idx}. {lang}')\n"
        . "    print('Ready to explore? Pick a demo course below!')\n\n"
        . "demo_preview()\n",
        ENT_QUOTES
    );

    $hero = '<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #2563eb 100%); color: #ffffff;">'
        . '<div class="card-body p-4 p-md-5">'
        . '<div class="row align-items-center">'
        . '<div class="col-lg-7">'
        . '<div class="d-flex flex-wrap gap-2 mb-3">'
        . '<span class="badge bg-white bg-opacity-25 text-white px-3 py-2 rounded-pill fw-normal" style="backdrop-filter: blur(4px);">Official Demo for CodeLab Plugins</span>'
        . '<span class="badge bg-info bg-opacity-25 text-white px-3 py-2 rounded-pill fw-normal">8 Languages + SQL</span>'
        . '<span class="badge bg-warning bg-opacity-25 text-white px-3 py-2 rounded-pill fw-normal">Line-by-Line Debugger</span>'
        . '<span class="badge bg-success bg-opacity-25 text-white px-3 py-2 rounded-pill fw-normal">VS Code Themes (Light/Dark/HC)</span>'
        . '<a href="' . $en_guide_url . '" class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold text-decoration-none shadow-sm">Admin &amp; Runner Guide →</a>'
        . '</div>'
        . '<h1 class="display-6 fw-bold text-white mb-3">Runnable Code in Moodle with the VS Code Editor</h1>'
        . '<p class="lead mb-4" style="color: rgba(255,255,255,0.92) !important; font-size: 1.15rem;">Write examples in C, C++, Python, Java, C#, JavaScript, Pascal, PHP and SQL. Choose from VS Code themes (Light, Dark, High Contrast), edit and run with live stdin, step through line-by-line with a debugger, and be graded automatically in quizzes.</p>'
        . '<div class="d-flex flex-wrap gap-3 mb-2">'
        . '<a href="' . $en_url . '" class="btn btn-light btn-lg px-4 fw-bold shadow-sm" style="border-radius: 12px; color: #1e3a8a;">Open English Demo</a>'
        . '<a href="' . $sr_url . '" class="btn btn-outline-light btn-lg px-4 fw-bold shadow-sm" style="border-radius: 12px; border-width: 2px;">Otvori kurs na srpskom</a>'
        . '<a href="' . $en_guide_url . '" class="btn btn-warning text-dark btn-lg px-4 fw-bold shadow-sm" style="border-radius: 12px;">Admin &amp; Teacher Guide</a>'
        . '</div>'
        . '</div>'
        . '<div class="col-lg-5 mt-4 mt-lg-0">'
        . '<div class="p-3 bg-dark bg-opacity-75 rounded-4 shadow-lg border border-white border-opacity-10">'
        . '<div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom border-white border-opacity-10">'
        . '<div class="small fw-bold text-white">Try it live now (no login required):</div>'
        . '<span class="badge bg-success bg-opacity-50 text-white font-monospace small">VS Code Dark Theme</span>'
        . '</div>'
        . '<pre class="codelab codelab-lang-python codelab-run codelab-theme-vs-dark" style="margin:0; font-size:13px; max-height:220px;">' . $live_code . '</pre>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '</div></div>'
        // Video tour (the media filter turns the link into a YouTube player).
        . '<div class="card border-0 shadow-sm rounded-4 mb-4 p-3 p-md-4">'
        . '<h4 class="fw-bold mb-1">Video tour: CodeLab for Moodle</h4>'
        . '<p class="text-muted mb-3">A full walkthrough: examples in the code editor, Run on Jobe or the runner, the debugger, flowcharts, the hosted runner and graded quiz questions.</p>'
        . '<div class="demo-video" style="max-width: 960px;">'
        . '<a href="https://youtu.be/2pegPwTl8eM#d=960x540">CodeLab for Moodle: full walkthrough (Jobe, hosted runner, flowchart and auto-grading)</a>'
        . '</div>'
        . '</div>'
        // Dual-Language Cards
        . '<div class="row g-3 mb-4 mx-0">'
        . '<div class="col-md-6">'
        . '<div class="card h-100 border-0 shadow-sm p-4 demo-course-card" style="background:#ffffff; border-left: 5px solid #2563eb !important;">'
        . '<div class="d-flex justify-content-between align-items-start mb-2">'
        . '<h4 class="fw-bold mb-0 text-primary">CodeLab: Demo (English)</h4>'
        . '<span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill">CODELAB-DEMO</span>'
        . '</div>'
        . '<p class="text-muted mb-3">Explore programming lessons in C, C#, Python and SQL. Includes interactive assignments, the step-by-step factorial debugger, and autograded quizzes.</p>'
        . '<a href="' . $en_url . '" class="btn btn-primary fw-bold px-4 rounded-3 align-self-start">Enter English Course →</a>'
        . '</div></div>'
        . '<div class="col-md-6">'
        . '<div class="card h-100 border-0 shadow-sm p-4 demo-course-card" style="background:#ffffff; border-left: 5px solid #059669 !important;">'
        . '<div class="d-flex justify-content-between align-items-start mb-2">'
        . '<h4 class="fw-bold mb-0 text-success">CodeLab: Demo (srpski)</h4>'
        . '<span class="badge bg-success bg-opacity-10 text-success px-3 py-1 rounded-pill">CODELAB-DEMO-SR</span>'
        . '</div>'
        . '<p class="text-muted mb-3">Kompletne lekcije i zadaci iz C i C# jezika, MySQL biblioteka baza, debager za C i Python red po red, i automatski ocenjivani kvizovi sa testovima.</p>'
        . '<a href="' . $sr_url . '" class="btn btn-success fw-bold px-4 rounded-3 align-self-start">Otvori kurs na srpskom →</a>'
        . '</div></div>'
        . '</div>'
        // Test Accounts Strip
        . '<div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-light">'
        . '<div class="row g-2 align-items-center text-center text-md-start mx-0">'
        . '<div class="col-md-3 fw-bold text-dark">Instant Demo Access:</div>'
        . '<div class="col-md-3"><div class="p-2 px-3 bg-white rounded-3 shadow-sm border small"><strong>Guest:</strong> Run &amp; step through all code examples</div></div>'
        . '<div class="col-md-3"><div class="p-2 px-3 bg-white rounded-3 shadow-sm border small"><strong>Student:</strong> <code>student</code> / <code>Demo-2026</code></div></div>'
        . '<div class="col-md-3"><div class="p-2 px-3 bg-white rounded-3 shadow-sm border small"><strong>Teacher:</strong> <code>teacher</code> / <code>Demo-2026</code></div></div>'
        . '</div></div>'
        // 3 Plugin Cards
        . '<div class="row g-3 mb-4 mx-0">'
        . '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm rounded-3 p-3"><div class="d-flex align-items-center mb-2"><h5 class="card-title mb-0 fw-bold"><a href="https://marketplace.moodle.com/plugins/tiny_codelab" target="_blank" class="text-decoration-none">tiny_codelab</a></h5></div><p class="card-text text-muted small mb-0">Insert runnable examples in any Moodle text with the code editor of VS Code: typing help, tooltips for beginners, zoom, Run on Jobe, the CodeLab runner or Python in the browser.</p><p class="small mb-0 mt-2"><a href="https://marketplace.moodle.com/plugins/tiny_codelab" target="_blank">Moodle Marketplace</a> &middot; <a href="https://github.com/blagojevicboban/moodle-tiny_codelab" target="_blank">GitHub</a></p></div></div>'
        . '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm rounded-3 p-3"><div class="d-flex align-items-center mb-2"><h5 class="card-title mb-0 fw-bold"><a href="https://marketplace.moodle.com/plugins/filter_codelab" target="_blank" class="text-decoration-none">filter_codelab</a></h5></div><p class="card-text text-muted small mb-0">Turns examples into editors readers can run: input while the program runs, debugger with step back and timeline, memory view, flowcharts, complexity charts and a class view.</p><p class="small mb-0 mt-2"><a href="https://marketplace.moodle.com/plugins/filter_codelab" target="_blank">Moodle Marketplace</a> &middot; <a href="https://github.com/blagojevicboban/moodle-filter_codelab" target="_blank">GitHub</a></p></div></div>'
        . '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm rounded-3 p-3"><div class="d-flex align-items-center mb-2"><h5 class="card-title mb-0 fw-bold"><a href="https://marketplace.moodle.com/plugins/qtype_codelab" target="_blank" class="text-decoration-none">qtype_codelab</a></h5></div><p class="card-text text-muted small mb-0">Programming questions graded automatically on tests: weights, hidden tests, code before and after, number and regex comparisons, SQL questions and Parsons problems.</p><p class="small mb-0 mt-2"><a href="https://marketplace.moodle.com/plugins/qtype_codelab" target="_blank">Moodle Marketplace</a> &middot; <a href="https://github.com/blagojevicboban/moodle-qtype_codelab" target="_blank">GitHub</a></p></div></div>'
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
