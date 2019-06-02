{menu:}
	<div class="mb-3">
		{(:/-showcase/tables):link}Данные{:/link} |
		{(:/-showcase/prices):link}Прайсы{:/link} |
		{(:/-showcase/groups):link}Группы{:/link} |
		{(:/-showcase/producers):link}Производители{:/link} |
		{(:/-showcase/models):link}Модели{:/link} |
		{(:/-showcase/api):link}API{:/link}
		<span class="float-right">Загрузить с Яндекс Диска
		<a class="btn btn-outline-info btn-sm" href="/-showcase/tables?-ydisk=tables">Данные</a>
		<a class="btn btn-outline-info btn-sm" href="/-showcase/prices?-ydisk=prices">Прайсы</a>
		<a class="btn btn-outline-info btn-sm" href="/-showcase/prices?-ydisk=true">Всё</a> 
		<small><a href="/">{View.getHost()}</a></small>
		</span>
	</div>
	{res:res}
	{link:}<a class="{location.pathname=.?:font-weight-bold}" href="{.}">{/link:}</a>
{root:}
	{:menu}
	<div class="card mt-3">
		<div class="card-body">
			Всего в каталоге <b>{count} {~words(count,:модель,:модели,:моделей)}</b>.<br>
		</div>
	</div>

	<hr class="mt-5">
	<h1>Технические команды</h1>
	<a class="btn btn-secondary" href="/-showcase/drop">Удалить данные и пересоздать базу данных</a>
	<hr>
{res:}<div style="font-size:80%" class="mt-2 alert alert-success">{::reskeys}</div>
	{reskeys:}{~typeof(.)=:object?:resobj?:resstr}
	{resstr:}<b>{~key}</b>: {.}<br>
	{resobj:}<div onclick="$(this).next().slideToggle()"><span class="a">{~key}</span>: {~length(.)}</div><div style="display: none">{~print(.)}</div>
{MODELS:}
	{:menu}
	<h1>Модели</h1>
	{list::model}
	{model:}
		<div class="mb-2" style="clear:both">
			<a title="Позиций {count}" href="/-showcase/api/pos/{producer_nick}/{article_nick}">{producer_nick} {article_nick}</a>{img:pic}
			<div><small>{Цена?:cost} <i>{group}</i> Данные: {catalog}</small></div>
		</div>
	{pic:} <small class="a" onclick="$(this).addClass('float-right').removeClass('a').html('<img src=\'/-imager/?src={.}&w=100\'>&nbsp;')">image</small>
	{cost:}{~cost(Цена)} руб.
{PRODUCER:}
		{:menu}
		<img class="float-right" src='/-imager/?src={logo}&w=100'>
		<h1>{producer}</h1>
		<div class="alert alert-{cls}">{:prodinfo}</div>
		
		<div class="mt-2 text-right" style="clear:both">{:actbunch}</div>
		<h2>Данные</h2>
		{clist::itemcatalog}
		<h2>Прайсы</h2>
		{plist::itemprice}
		
		{:foot}
	{skip:}
	<span class="a" onclick="$(this).next().slideToggle()">skip</span>.
	<div style="display:none">{~print(.)}</div>
	{prodinfo:}Моделей: <b><a href="/catalog/{producer_nick}">{count}</a></b>, без цен: <b><a href="/catalog/{producer_nick}?m=:more.Цена.no=1">{Без цен}</a></b>, без картинок: <b><a href="/catalog/{producer_nick}?m=:more.images.no=1">{Без картинок}</a></b>, {skip:skip}
{PRODUCERS:}
		{:menu}
		<h1>Производители</h1>
		{list::producer}
	{producer:}<div class="alert alert-{cls}"><a href="/-showcase/producers/{producer_nick}">{producer}</a> <div class="float-right">{logo:pic}</div> {:prodinfo}</div>
{GROUPS:}
	{:menu}
	<h1>Группы</h1>
	{list.childs::groups}
	<br><br><br><br>
	{groups:}
		<div>
		{~length(childs)?:subgroup?:justgroup}
	</div>
		{subgroup:}
		<div><span class="a" onclick="$(this).parent().parent().find('.sub:first').slideToggle()">{group}</span> <small> ({group_nick}) {catalog}.xlsx</small> <b>{sum}</b>{icon:pic}</div>
		<div class="ml-4 sub" style="display:none">{childs::groups}</div>
		{justgroup:}
		<div>{group}</span> <small> ({group_nick}) {catalog}.xlsx</small> <b>{count}</b>{icon:pic}</div>
{PRICE:}
		{:menu}
		<h1>{file}</h1>
		{:itemprice}
		{PRICEhide:}
		<hr>
		<h2>Не найдено в данных</h2>
		{missdata::missdata}
		<h2>Пропущено в прайсе</h2>
		{missprice::misspirce}
		{:foot}
	{missdata:}
	<span class="a" data-toggle="collapse" data-target="#collapsedata{~key}">{.[priceprop]}</span><br>
	<div class="collapse" id="collapsedata{~key}">{~print(.)}</div>
	{misspirce:}
	<span class="a" data-toggle="collapse" data-target="#collapseprice{~key}">{article}</span><br>
	<div class="collapse" id="collapseprice{~key}">{~print(.)}</div>
{PRICES:}
		{:menu}
		<h1>Прайсы</h1>
		{list::itemprice}
		
		<hr>
		<div class="d-flex justify-content-between">
			<div>
				<span class="btn btn-primary" onclick="ActionPrice('loadAll')">Внести все прайсы</span>
			</div>
			<div>
				<span class="btn btn-danger" onclick="ActionPrice('clearAll')">Очистить всё</span>
			</div>
		</div>
		{:foot}
{CATALOG:}
		{:menu}
		<h1>Данные</h1>
		{list::itemcatalog}
		
		<hr>
		<div class="d-flex justify-content-between">
			<div>
				<!--<a href="/-showcase/update" class="btn btn-primary">Внести все новые данные и прайсы</a>-->
				<span class="btn btn-primary" onclick="ActionTable('loadAll')">Внести все данные</span>
				<span class="btn btn-info" onclick="ActionTable('addFilesAll')">Связать всё с файлами</span>
			</div>
			<div>
				<span class="btn btn-danger" onclick="ActionTable('clearAll')">Очистить всё</span>
			</div>
		</div>
		{:foot}
	{time:}<b title="{~date(:H:i,.)}">{~date(:d.m,.)}</b>, 
	{size:}<b>{.}</b> Кб, 
	{duration:}Загрузка за <b title="{.}">{.<:1?:1?.}</b> сек,
	{icount:}
		Всего: <b>{ans.Количество позиций в прайсе}</b>, не найдено: <b>{~length(ans.Не найдено соответствий)}</b>, пропущено: <b>{~length(ans.У позиции в прайсе не указан ключ)}</b><br>
	{ptitle:}{producer?:linkproducer?(:Общий прайс для всех производителей):com}
	{ctitle:}{producer?:linkproducer?(:Общие данные для всех производителей):com}
	{linkproducer:}Производитель: <a href="/-showcase/producers/{producer_nick}">{producer}</a>
	{itemname:}<b><a href="/-showcase/prices/{name}">{file|:Нет файла}</a></b><br>
	{itemname:}<b>{file|:Нет файла}</b><br>
	{notime:}файл не внесён,
	{noans:}раньше не вносился.
	{noduration:}Время загрузки не известно,
{itemprice:}
	<div class="d-flex table {mtime>time?:bg-warning} rounded">
		<div class="p-2" style="width:220px">
			{:itemname}
			{:ptitle}
			<i></i><br>{size:size} {mtime:time}
		</div>
		<div class="p-2 flex-grow-1">
			{duration?duration:duration?:noduration} {time?time:time?:notime} {ans?ans:res?:noans}
			
		</div>
		
		{:pactions}
		
	</div>
	
	{showopt:}
	<span class="a" onclick="$(this).next().slideToggle()">Есть опции</span>
	<div style="display:none">
	<b>Синонимы</b> {~print(synonyms)}
	<b>Параметры</b> {~print(props)}
	</div>
	{com:}<b class="text-danger">{.}</b>
	{price-count:}Обработано <b>{count}</b> {~words(count,:строка,:строки,:строк)} с ключём <b>{priceprop}</b>.
{itemcatalog:}
	<div class="d-flex table {mtime>time?:bg-warning} rounded">
		<div class="p-2" style="width:220px">
			<b>{file|:Нет файла}</b><br>
			{:ctitle}<br>
			{size:size} {mtime:time}
		</div>
		<div class="p-2 flex-grow-1">
			{duration?duration:duration?:noduration} {time?time:time?:notime} {ans?ans:res?:noans}
		</div>
		{:cactions}
	</div>

	{ans:}
	<span class="a" onclick="$(this).next().slideToggle()">{..time??:прошлый }ответ</span>.
	<div style="display:none" class="alert alert-success">{~print(.)}</div>
	{catcount:}Принято: <b>{icount}</b> {~words(icount,:позиция,:позиции,:позиций)}<br>
	
{pactions:}
		<div class="p-2 text-right" style="width:240px">
			{file?:actpload}
			{isdata?:actdelprice}
		</div>
	{actpload:}<span class="btn btn-primary" onclick="ActionPrice('load','{name}','{conf.prices}{file}')">Внести</span>
	{actdelprice:}<span class="btn btn-danger" onclick="ActionPrice('remove','{name}','{conf.prices}{file}')">Очистить</span>
{cactions:}
		<div class="p-2 text-right" style="width:400px">
			{file?:actfile}
			{plist??(time?:actbunch)}
			{time?:actdel}
		</div>
	{actbunch:}<span class="btn btn-info" onclick="Action('addFiles','{producer_nick}')">Связать с файлами</span>
	{actfile:}<span class="btn btn-primary" onclick="ActionTable('load','{name}','{conf.tables}{file}')">Внести</span>
	{actdel:}<span class="btn btn-danger" onclick="ActionTable('remove','{name}','{conf.tables}{file}')">Очистить</span>
{foot:}
	<hr>
	<form id="form" method="POST">
		<input id="formaction" type="hidden" name="action" value="">
		<input id="formsrc" type="hidden" name="src" value="">
		<input id="formtype" type="hidden" name="type" value="">
		<input id="formname" type="hidden" name="name" value="">
	</form>
	<script>
		Action = function(action, name, src, type) {
			if (src) formsrc.value = src;
			if (name) formname.value = name;
			if (type) formtype.value = type;
			formaction.value = action;
			form.submit();
		}
		ActionPrice = function(action, name, src) {
			if (src) formsrc.value = src;
			if (name) formname.value = name;
			formtype.value = 'price';
			formaction.value = action;
			form.submit();
		}
		ActionTable = function(action, name, src) {
			if (src) formsrc.value = src;
			if (name) formname.value = name;
			formtype.value = 'table';
			formaction.value = action;
			form.submit();
		}
	</script>