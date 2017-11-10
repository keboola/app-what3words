FROM composer
COPY . /code/
WORKDIR /code/
RUN composer install --no-interaction
CMD ["php", "/code/main.php"]
