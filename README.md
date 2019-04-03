

GROUPS		group_id, group_name, title, parent_id
PROPS 		prop_id, prop_name, [prop_nick], type (number, string) - number считаются только те свойство которые указаны в конфиге
STRINGS 	string_id, value, [string_nick] - string_nick создаётся для тех свойств которые есть в фильтрах конфига

====
SOURCES 	source_id, filename, date, type - price, catalog 
SOURCEITEMS	pitem_id, source_id, key_value, key_id
SIPROPS		pitem_id, prop_id, number, string_id - Для каталога все, для прайса выборочно
====

MODELS		model_id, group_id, source_id, [producer, article]
ITEMS		item_id, model_id, [item]
ITEMPROPS	item_id, prop_id, source_id, number, string_id


Конфиг прайса	(producer, isglob, isaccurate, catalogkeytpl, pricekeytpl, price_prop, catalog_prop в конфиге)
	true, false		- pricekey_value глобальный, 
	true, true 		- pricekey_id по priceprop_id, catalogprop_id, глобальный поиск
	false, false 	- pricekey_value уникальный для producer
	false, true	 	- pricekey_id по priceprop_id, catalogprop_id, уникальный для producer
	parse - заменяется с обновлением прайса, удаляется с пропажей прайса		

	

	Применяется загруженный ранее прайс
	1) Бежим по всем данным producer или глобально в php fetch запрос, генерируем catalogkey_value и сравниваем с key_value
	Если совпало в каталоге заменяем или добавляем значения prop_id на value и создаём string_id если type не 1

	2) Есть price_prop, catalog_prop и number/string_id из прайса по которому update делается одним запросом и для всех props модели устанавливается новый  number/string_id


-showcase/

Применить все обновления
Перепривязать все файлы

Данные
Файл		файл/загружен 	действия
asdf.xlsx 	----/date 		применить
asdf.xlsx 	date/date 		применить
asdf.xlsx 	date/date 		применить
asdf.xlsx 	date/date 		применить

Прайсы
Файл		файл/загружен 	действия
asdf.xlsx 	----/date 		применить
asdf.xlsx 	date/date 		применить
asdf.xlsx 	date/date 		применить
asdf.xlsx 	date/date 		применить

Конфиг ~prices.json
<pre>

showcase: {
	"src":"~catalog/tables/",
	"numbers":["Цена"],
	"filters":{ //Если указано то для этих свойств для строк и bool создаётся string_nick, заполняется filters
		"светильники-и-прожектора":["источник-света","степень-защиты"]
	},
	prices: {
		name: {
			"producer":"RPM",
			"price":"....",
			"catalog":"..."
			"price_prop":"Артикул",
			"catalog_prop":"Код"
		}
	}
}
</pre>

-showcase/:post?action=do-catalog&src=src
	do catalog = load, files, apply prices
-showcase/:post?action=do-price&src=src
	do price = load, apply

-showcase/search/
Каждые 24 часа удалять метки, которые старее 96 часов
-showcase/groups/

Showcase::parseNew();


БЛОКИ ОПЕРАЦИЙ
- Загрузить данные
- Найти файлы данных (images, files, texts)
- Загрузить прайс
- Применить прайс


- Удалить данные
- Удалить прайс

- Очистить
- Загрузить и применить всё новое
- Удалить все данные

- Привязать файлы (Несколько значений у свойства)

















