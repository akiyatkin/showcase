
showcase_prices 		*price_id, [name], producer_id, time, order, count, duration
showcase_catalog 		*catalog_id, [name], producer_id, time, order, count, duration
===
showcase_groups			*group_id, group, [group_nick], parent_id
showcase_producers 		*producer_id, producer, [producer_nick]
showcase_articles 		*article_id, article, [article_nick]


showcase_props 			*prop_id, prop, [prop_nick], type (1 value, 2 number, 3 text) - number и text считаются только те свойство которые указаны в конфиге
showcase_values 		*value_id, value, [value_nick] - value_nick создаётся для тех свойств которые есть в фильтрах конфига
====

showcase_items 			(model_id, item_num), item, [item_nick]
showcase_models			*model_id, catalog_id, [producer_id, article_id], group_id, time (1 актив, 2 удалена - для сохранения ид)

showcase_mvalues		[model_id, item_num, prop_id, value_id], price_id, order
showcase_mnumbers		[model_id, item_num, prop_id, number], price_id, order
showcase_mtexts			[model_id, item_num, prop_id], text, price_id, order


Если удалили колонку и у айтема пропал props - удаляются все пропсы модели, кроме тех у которых price_id

Нужно точно знать какие свойства относятся к mitem а какие к model. 

Конфиг прайса	(producer, isglob, isaccurate, catalogkeytpl, pricekeytpl, price_prop, catalog_prop в конфиге)
	true, false		- pricekey_value глобальный, 
	true, true 		- pricekey_id по priceprop_id, catalogprop_id, глобальный поиск
	false, false 	- pricekey_value уникальный для producer
	false, true	 	- pricekey_id по priceprop_id, catalogprop_id, уникальный для producer
	parse - заменяется с обновлением прайса, удаляется с пропажей прайса		

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
			"price_key":"....",
			"catalog_key":"..."
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

-showcase/search
-showcase/pos/producer/article
-showcase/groups
-showcase/producers
Каждые 24 часа удалять метки, которые старее 96 часов


Showcase::parseNew();


Длинное имя группы, например: "Автомобильные регистраторы #avtoreg" берётся из Наименования в descr. Id encod(всё) title то что до решётки.


Связь с файлами рассчитывается по производителям.
- Выгружаются все артикулы производителя. 
- Индексируются все файлы имеющие отношение к производителю, имя файла считается артикулом(по запятым несколько артикулов) и по ассоциативному массиву с артикулами позиций вносятся изменения в базу данных. Для этих опций указывается price_id 1 и файлы не будут удалятся при внесении обновлённых данных.

# Фото, Файл, Файлы, Иллюстрации
Фото, Файл - шаблон имени файла
Иллюстрации - Путь до иллюстрации в интернете, как есть попадает в images

# justonevalue
numbers и values по умолчанию сплитятся по запятым. Это поведение можно для какого-то свойства отменить указав его в свойстве justonevalue.

# specprice
Свойство specprice у позиции в каталоге делает цену каталога приоритетней цены прайса

# Пример опций ~showcase.json
```json
{
	"numbers":["Цена (опт 1)", "Цена (опт 2)","Цена (розница)","Код"],
	"values":["Ед.","Применяемость","Наличие на складе","Доп. ВПР (ТЛТ)"],
	"texts":["Описание","Наименование"],
	"justonevalue":["Наименование"],
	"filters":{
		"светильники-и-прожектора":["источник-света","степень-защиты"]
	},
	"catalog":{
		"Nokia":{
		},
		"El-car":{
			"producer":"ЭЛКАР"
		}
	},
	"prices":{
		"El-car":{
			"synonyms": {
				"Опт":["опт"],
				"Розница":["розница","Розничная цена, руб"]
			},
			"props":["Артикул","Производитель"],
			"producer":false,
			"price_prop":"Код",
			"catalog_prop":"Код"
		},
		"Amatek": {
			"start":4,
			"synonyms": {
				"Опт":["опт"],
				"Розница":["розница","Розничная цена, руб"]
			},
			"price":"{Path.encode(Модель)}",
			"catalog":"{article}",
			"ignore":["расшифровка  обозначений","Выбор модели HVR 2018", "Выбор модели HVR 2019", "Выбор модели HVR 2017","Режимы HVR","Режимы NVR"]
		},
		"RVi": {
			"start":2,
			"synonyms":{
				"Розничная цена":["РОЗНИЧНАЯ ЦЕНА"]
			},
			"ignoreart":["1-4-Объективы"],
			"merge":true,
			"price":"{Path.encode(Наименование)}",
			"catalog":"{article}",
			"ignore":["Оглавление","Совместимость доп. аксессуаров", "Совместимость доп. аксессуаров","!АКЦИИ","Выбор модели HVR","Оглавление"]
		},
		"Ritm": {
			"start":7,
			"price":"{Path.encode(Номенклатура)}",
			"catalog":"{article}"
		},
		"Nice": {
			"price":"{Path.encode(Артикул)}",
			"catalog":"{article}",
			"ignore":["Старт","Откатные ворота","Распашные ворота","Шлагбаумы","Секционные ворота","Радиоуправление","Внутривальные приводы","Прайс-лист на запчасти"]
		},
		"Линия": {
			"start":7,
			"head":["Артикул","","Описание","Цена"],
			"price":"{Path.encode(Артикул)}",
			"catalog":"Линия-{article}",
			"ignore":["Лист2"]
		},
		"Optimus": {
			"ignore":["Содержание"],
			"start":2,
			"price":"{код|Код}",
			"catalog":"{КодПрайса}"
		},
		"Tantos": {
			"start":6,
			"ignoreart":["мониторы-с-кнопочным-управлением",
				"мониторы-с-сенсорным-экраном",
				"ts-exit-выводится-из-ассортимента",
				"катушка-для-ts-el2369st-ss-и-ts-el2370ss"],
			"synonyms": {
				"Наименование":["Наименование товаров"],
				"Опт.":["опт."],
				"Розн.":["розн."]
			},
			"ignore":["Разъёмы и соединители"],
			"start":6,
			"price":"{~lower(Path.encode(Наименование))}",
			"catalog":"{~lower(article)}"
		},
		"O-ZERO": {
			"ignore":["Главная"],
			"start":2,
			"price":"{Path.encode(Наименование)}",
			"catalog":"{article}"
		}
	}
}
```




