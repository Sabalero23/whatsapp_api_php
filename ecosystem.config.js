// ecosystem.config.js
module.exports = {
  apps: [
    {
      name: 'whatsapp-account-1',
      script: './server.js',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
      env: {
        NODE_ENV: 'production',
        ACCOUNT_ID: '1',
        PORT: 3000,
        SESSION_NAME: 'whatsapp-session-1'
      }
    },
    {
      name: 'whatsapp-account-2',
      script: './server.js',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
      env: {
        NODE_ENV: 'production',
        ACCOUNT_ID: '2',
        PORT: 3001,
        SESSION_NAME: 'whatsapp-session-2'
      }
    },
    {
      name: 'whatsapp-account-3',
      script: './server.js',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
      env: {
        NODE_ENV: 'production',
        ACCOUNT_ID: '3',
        PORT: 3002,
        SESSION_NAME: 'whatsapp-session-3'
      }
    }
  ]
};