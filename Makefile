.PHONY: up down build shell composer artisan migrate seed fresh test npm logs s3-ls s3-shell

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

shell:
	docker compose exec app bash

composer:
	docker compose exec app composer $(filter-out $@,$(MAKECMDGOALS))

artisan:
	docker compose exec app php artisan $(filter-out $@,$(MAKECMDGOALS))

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

fresh:
	docker compose exec app php artisan migrate:fresh --seed

test:
	docker compose exec app php artisan test

npm:
	docker compose exec node npm $(filter-out $@,$(MAKECMDGOALS))

logs:
	docker compose logs -f $(filter-out $@,$(MAKECMDGOALS))

s3-ls:
	docker compose exec localstack sh -lc 'awslocal s3 ls && echo && awslocal s3 ls s3://$${AWS_BUCKET:-docintern-dev}'

s3-shell:
	docker compose exec localstack sh

%:
	@:
