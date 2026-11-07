module.exports = {
  apps: [{
    name: 'codelab-webhook',
    script: 'deploy/webhook-server.js',
    cwd: __dirname + '/..',
    env: {
      NODE_ENV: 'production',
      WEBHOOK_PORT: 9010,
      WEBHOOK_SECRET: process.env.WEBHOOK_SECRET || 'codelab-demo-secret-2026',
      TARGET_BRANCH: 'main'
    },
    autorestart: true,
    watch: false,
    max_memory_restart: '150M'
  }]
};
