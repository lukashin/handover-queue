# handover-queue


# Создание нового элемента очереди

```
curl 'http://eq.znak-corp.ru/warehouse/dbc03d32-a688-481c-87af-1d98176c8987/new/179e7ae8-5632-41e6-ad69-a9a1d08cd259' -H 'Content-Type: application/x-www-form-urlencoded' --data 'caption=%D0%9E%D0%9F%D0%90-009'

```

```
curl -X post 'http://eq.znak-corp.ru/warehouse/dbc03d32-a688-481c-87af-1d98176c8987/new/179e7ae8-5632-41e6-ad69-a9a1d08cd259/%D0%9E%D0%9F%D0%90-009'

```
Где URL состоит из следующих сегментов
`/warehouse/{warehouseUuid}/{status}/{itemUuid}/{caption}`


--------------|-------------------------------|------------------------------
warehouseUuid | UUIDv4 - идентификатор склада | dbc03d32-a688-481c-87af-1d98176c8987




