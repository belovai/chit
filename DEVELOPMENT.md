## Development

### Local env

URL: http://chit.127.0.0.1.nip.io/

### Code style
```bash
docker compose exec php bash -c "./vendor/bin/pint"
```

### Helper
```bash
docker compose exec php php artisan ide-helper:models -RN
```

## Static analysis
```bash
docker compose exec php ./vendor/bin/phpstan analyse --memory-limit=2G
```
