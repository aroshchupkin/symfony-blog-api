Реализовать простое API

Основные требования:
- Использование PHP 8.1 и выше, Symfony 6 и выше, Postgresql, Docker
- Приложение должно работать в docker контейнерах
- Код должен быть выложен в git с нормальными коммитами и историей разработки
- В проекте должна лежать коллекция(postman) с методами


Нужно реализовать api для сущностей:
- Реализовать CRUD
    * Статья (Post): добавить, удалить, обновить, получить одну, получить все с пагинацией и кешем
    * Комментарий (Comment): добавить к статье, получить все комментарии к статье с пагинацией, удалить коментарий
- Реализовать валидацию полей
- Четкие ответы с HTTP статусами и JSON ответы с описанием ошибок


Дополнительные требования (со звездочкой)
- Реализовать регистрацию\аутентификацию пользователя
    * Поля: id, username, email, password, created_at
    * регистрация по пути /registration, аутентификация по пути /login
    * а так же добавить проверку токена на методы api
    * добавить сохранение пользователя в бд, кто оставляет комментарий, кто созадет пост
- Описать Api в формате документации swagger\openApi



Проект дожен запускаться 1 командой (docker compose up -d)

**SwaggerUI**:
- `http://localhost:8000/api/doc`

**Postman запросы**:
- `POST http://localhost:8000/api/registration` _// зарегистрировать пользователя_
- `POST http://localhost:8000/api/login` _// войти под зарегистрированным пользователем и вернуть JWT-токен_
- `GET http://localhost:8000/api/profile` _// профиль аутентифицированного пользователя (JWT)_
- `POST http://localhost:8000/api/posts` _// создать пост (JWT)_
- `POST http://localhost:8000/api/posts/{id}/comments` _// создать комментарий к посту с ID={id} (JWT)_
- `GET http://localhost:8000/api/posts` _// получить список созданных постов (JWT)_
- `GET http://localhost:8000/api/posts/{id}` _// получить пост по {id} (JWT)_
- `GET http://localhost:8000/api/posts/{postId}/comments` _// к посту {postId} получить список комментариев (JWT)_
- `GET http://localhost:8000/api/posts/{postId}/comments/{id}` _// к посту {postId} получить комментарий {id} (JWT)_
- `PATCH http://localhost:8000/api/posts/{id}` _// обновить данные к посту {id} (JWT)_
- `PATCH http://localhost:8000/api/posts/{postId}/comments/{id}` _// к посту {postId} обновить комментарий {id} (JWT)_
- `DELETE http://localhost:8000/api/posts/{id}` _// удалить пост по {id} (JWT)_
- `DELETE http://localhost:8000/api/posts/{postId}/comments/{id}` _// к посту {postId} удалить комментарий по {id} (JWT)_
