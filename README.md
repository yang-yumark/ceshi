// 创建队列表
php artisan queue:table
php artisan migrate

// Supervisor配置 - /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
command=php /path/to/artisan queue:work database --queue=balance_updates --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3 ; 根据负载调整
redirect_stderr=true
stdout_logfile=/var/log/worker.log
stopwaitsecs=60


// 启动Supervisor：
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*