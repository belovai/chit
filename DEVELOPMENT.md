## Development

### Local env

URL: http://chit.127.0.0.1.nip.io/

### Helper
```bash
docker compose exec php php artisan ide-helper:models -RW --no-interaction
```

### Code style
```bash
docker compose exec php bash -c "./vendor/bin/pint"
```

## Static analysis
```bash
docker compose exec php ./vendor/bin/phpstan analyse --memory-limit=2G
```

## Tests
```bash
docker compose exec php php artisan test
```
