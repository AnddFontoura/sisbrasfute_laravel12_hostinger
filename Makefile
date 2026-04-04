down:
	docker compose down -v --remove-orphans

up:
	docker compose up -d

restart:
	docker compose down -v --remove-orphans
	docker compose up -d

set-database:
	docker exec -it sisbrasfute_back php artisan migrate
	docker exec -it sisbrasfute_back php artisan db:seed

set-api:
	docker exec -it sisbrasfute_back php artisan
