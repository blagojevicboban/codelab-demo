#!/usr/bin/env node
/**
 * GitHub Webhook Receiver for CodeLab Demo
 * Listens for GitHub push events and executes deploy.sh
 */

const http = require('http');
const crypto = require('crypto');
const { execFile } = require('child_process');
const path = require('path');
const fs = require('fs');

const SECRET = process.env.WEBHOOK_SECRET || '';
const PORT = parseInt(process.env.WEBHOOK_PORT, 10) || 9010;
const TARGET_BRANCH = process.env.TARGET_BRANCH || 'main';
const DEPLOY_SCRIPT = path.join(__dirname, 'deploy.sh');
const LOG_FILE = path.join(__dirname, '..', 'logs', 'webhook.log');

try {
  const logsDir = path.dirname(LOG_FILE);
  if (!fs.existsSync(logsDir)) {
    fs.mkdirSync(logsDir, { recursive: true });
  }
} catch (err) {
  console.error('Cannot create logs dir:', err.message);
}

function log(msg) {
  const line = `[${new Date().toISOString()}] ${msg}\n`;
  process.stdout.write(line);
  try {
    fs.appendFileSync(LOG_FILE, line);
  } catch {}
}

function verifySignature(payload, signature) {
  if (!SECRET) return true;
  if (!signature) return false;
  const hmac = crypto.createHmac('sha256', SECRET);
  const digest = 'sha256=' + hmac.update(payload).digest('hex');
  try {
    return crypto.timingSafeEqual(Buffer.from(digest), Buffer.from(signature));
  } catch {
    return false;
  }
}

let isDeploying = false;

function runDeploy() {
  if (isDeploying) {
    log('Deploy already in progress, skipping duplicate event.');
    return;
  }
  isDeploying = true;
  log('Starting deploy.sh...');

  execFile('/bin/bash', [DEPLOY_SCRIPT], { timeout: 300000 }, (error, stdout, stderr) => {
    isDeploying = false;
    if (error) {
      log(`Deploy failed: ${error.message}\nStderr: ${stderr}`);
      return;
    }
    log(`Deploy completed successfully:\n${stdout}`);
  });
}

const server = http.createServer((req, res) => {
  if (req.method === 'GET' && req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('ok');
    return;
  }

  if (req.method !== 'POST' || (req.url !== '/webhook' && req.url !== '/')) {
    res.writeHead(404);
    res.end('Not found');
    return;
  }

  let body = '';
  req.on('data', chunk => { body += chunk.toString(); });

  req.on('end', () => {
    const signature = req.headers['x-hub-signature-256'];
    const event = req.headers['x-github-event'];

    if (!verifySignature(body, signature)) {
      log('Rejected: invalid HMAC signature.');
      res.writeHead(401, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ error: 'Invalid signature' }));
      return;
    }

    if (event === 'ping') {
      log('Received GitHub ping event.');
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ message: 'pong' }));
      return;
    }

    if (event !== 'push') {
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ message: 'Ignored non-push event' }));
      return;
    }

    try {
      const payload = JSON.parse(body);
      const branch = payload.ref ? payload.ref.replace('refs/heads/', '') : '';

      if (branch !== TARGET_BRANCH) {
        log(`Ignored push to branch ${branch} (expecting ${TARGET_BRANCH}).`);
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ message: `Ignored branch ${branch}` }));
        return;
      }

      log(`Push event on ${branch} by ${payload.pusher ? payload.pusher.name : 'unknown'}. Triggering deploy...`);
      res.writeHead(202, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ message: 'Deploy triggered' }));

      runDeploy();
    } catch (err) {
      log(`Failed to parse webhook JSON: ${err.message}`);
      res.writeHead(400, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ error: 'Malformed JSON' }));
    }
  });
});

server.listen(PORT, '127.0.0.1', () => {
  log(`CodeLab Webhook Server listening on 127.0.0.1:${PORT}`);
});
