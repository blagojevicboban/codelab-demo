# CodeLab Demo Courses (codelab.tsp.edu.rs)

Public demo repository for the CodeLab suite of Moodle plugins:
- [tiny_codelab](https://github.com/blagojevicboban/moodle-tiny_codelab): Visual code editor in TinyMCE (VS Code Monaco editor).
- [filter_codelab](https://github.com/blagojevicboban/moodle-filter_codelab): Code filter that turns static code blocks into runnable editors, step-by-step debuggers, and SQL sandboxes.
- [qtype_codelab](https://github.com/blagojevicboban/moodle-qtype_codelab): Automatically graded programming questions for Moodle quizzes.

## Contents

- `demo.php`: Automated CLI restore script that setups the site, customizes the Boost theme, sets cross-linking banners, updates the front page with a live Python runner and dual course cards, and enrolls demo users (`teacher` / `student`).
- `codelab-demo.mbz`: English demo course (`CODELAB-DEMO` – *CodeLab: demo (English)*).
- `codelab-demo-sr.mbz`: Serbian demo course (`CODELAB-DEMO-SR` – *CodeLab: demo (srpski)*).
- `deploy/`: GitHub webhook server and automated deploy script.

## Webhook Auto-Deploy Setup (Virtualmin)

1. Clone this repository on the server:
   ```bash
   cd /home/codelab
   git clone https://github.com/blagojevicboban/codelab-demo.git demo-repo
   cd demo-repo
   ```

2. Start the webhook server under PM2:
   ```bash
   pm2 start deploy/ecosystem.config.js
   pm2 save
   ```

3. In Virtualmin Apache Configuration for `codelab.tsp.edu.rs`:
   Add inside the VirtualHost (or via Webmin → Apache Webserver):
   ```apache
   ProxyPass /webhook http://127.0.0.1:9010/webhook
   ProxyPassReverse /webhook http://127.0.0.1:9010/webhook
   ```

4. In GitHub Repo Settings → Webhooks:
   - **Payload URL:** `https://codelab.tsp.edu.rs/webhook`
   - **Content type:** `application/json`
   - **Secret:** `codelab-demo-secret-2026`
   - **Events:** Just the `push` event.

Now every `git push` to `main` will automatically update `codelab.tsp.edu.rs`!
