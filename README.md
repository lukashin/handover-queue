# Управление элементами очереди

Выполняется посредством POST запроса на указанный URL

## URL состоит из следующих сегментов
`http://eq.znak-corp.ru/warehouse/{warehouseUuid}/{status}/{itemUuid}/{caption}`

Сегмент    |  Описание |   Допустимые значения / пример
--------------|-------------------------------|------------------------------
warehouseUuid | UUIDv4 - идентификатор склада | dbc03d32-a688-481c-87af-1d98176c8987
status | статус позиции | new, assemblyInProgress, readyForPickup, handedOver, reset
itemUuid | UUIDv4 - идентификатор позиции (чека, отгрузки) | dbc03d32-a688-481c-87af-1d98176c8987
caption | Заголовок позиции, URL encoded | ЧЕК-210, УПД-001

При создании позиции `{caption}` параметр обязателен, однако рекомендуется передавать его с каждым запросом.

Приложение позволяет создавать позицию очереди в любом статусе: например, сразу в статусе "собирается".

Установка статуса "reset" приводит к удалению позиции из очереди.

## Пример простого запроса на создание элемета очереди
```
curl -X post 'http://eq.znak-corp.ru/warehouse/dbc03d32-a688-481c-87af-1d98176c8987/new/179e7ae8-5632-41e6-ad69-a9a1d08cd259/%D0%9E%D0%9F%D0%90-009'
```

## Параметр caption можно передать также как поле формы
```
curl 'http://eq.znak-corp.ru/warehouse/dbc03d32-a688-481c-87af-1d98176c8987/new/179e7ae8-5632-41e6-ad69-a9a1d08cd259' -H 'Content-Type: application/x-www-form-urlencoded' --data 'caption=%D0%9E%D0%9F%D0%90-009'
```

## Изменение статуса на "assemblyInProgress" - "Собирается"
```
curl -X post 'http://eq.znak-corp.ru/warehouse/dbc03d32-a688-481c-87af-1d98176c8987/assemblyInProgress/179e7ae8-5632-41e6-ad69-a9a1d08cd259'
```

## Ответ сервера при успешной обработке

`HTTP/1.1 201 Created` элемент создан

`HTTP/1.1 200 OK` элемент обновлен

`HTTP/1.1 204 No content` элемент удален (при установке статуса reset)

Body ответа содержит json представление обработанного элемента
```
< HTTP/1.1 200 OK
< Content-Type: application/json;charset=utf-8
{
    "uuid": "179e7ae8-5632-41e6-ad69-a9a1d08cd259",
    "status": "new",
    "updated": "2018-02-19 15:29:45",
    "caption": "ОПА-009",
    "created": "2018-02-19 20:55:44"
}
```

## Обработка ошибок

В случае ошибок при обработке данных
- не переданы обазятельные параметры
- не найден склад по переданному UUIDv4
- отсутствует параметр `{caption}` при создании нового элемента
- неверное значение параметра `{status}`

`HTTP/1.1 409 Conflict`

Body ответа содержит json представление информации об ошибках
```
{"errors":["parameter caption non-null value expected for status=new"]}
```

В случае критических ошибок в работе приложения: `500 Internal Server Error`



