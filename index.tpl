{menu:}
	<div class="mb-3">
		{(:/-showcase/tables):link}Данные{:/link} |
		{(:/-showcase/prices):link}Прайсы{:/link} |
		{(:/-showcase/groups):link}Группы{:/link} |
		{(:/-showcase/producers):link}Производители{:/link} |
		<!--{(:/-showcase/models):link}Модели{:/link}  -->
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
{res:}<div class="mt-2 alert alert-success">{~print(.)}</div>
{MODELS:}
	{:menu}
	<h1>Модели</h1>
	{list::model}
	{model:}
		<div class="mb-2" style="clear:both">
			<a title="Позиций {count}" href="/-showcase/api/pos/{producer_nick}/{article_nick}">{producer_nick} {article_nick}</a>{img:pic}
			<div><small>{Цена?:cost} <i>{group}</i> {catalog}.xlsx</small></div>
		</div>
	{pic:} <small class="a" onclick="$(this).addClass('float-right').removeClass('a').html('<img src=\'/-imager/?src={.}&w=100\'>&nbsp;')">image</small>
	{cost:}{~cost(Цена)} руб.
{PRODUCER:}
	{:menu}
	<h1>{producer}</h1>
	{icon:pic}
	{:prodinfo} <br>
	<!--Данные: {catalog}.xlsx<br>-->
	{prodinfo:}Моделей: <b><a href="/catalog/{producer_nick}">{count}</a></b>, без цены: <b><a href="/catalog/{producer_nick}?m=:more.Цена.no=1">{Без цены}</a></b>, без картинки: <b><a href="/catalog/{producer_nick}?m=:more.images.no=1">{Без картинки}</a></b>, {catalog}.xlsx {icon:pic}
{PRODUCERS:}
	{:menu}
	<h1>Производители</h1>
	{list::producer}
	{producer:}<a href="/-showcase/producers/{producer_nick}">{producer}</a> <small>{:prodinfo}</small><br>
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
			<span class="btn btn-primary" onclick="Action('loadAll')">Внести все прайсы</span>
		</div>
		<div>
			<span class="btn btn-danger" onclick="Action('clearAll')">Очистить всё</span>
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
			<span class="btn btn-primary" onclick="Action('loadAll')">Внести все данные</span>
			<span class="btn btn-info" onclick="Action('addFilesAll')">Связать всё с файлами</span>
		</div>
		<div>
			<span class="btn btn-danger" onclick="Action('clearAll')">Очистить всё</span>
		</div>
	</div>
	{:foot}
{time:}<b title="{~date(:H:i,.)}">{~date(:d.m,.)}</b>, 
{size:}<b>{.}</b> Кб, 
{duration:}Загрузка за <b title="{.}">{.<:1?:1?.}</b> сек,
{icount:}
	Всего: <b>{ans.Количество подходящих строк}</b>, не найдено: <b>{ans.Не найдено соответствий}</b>, пропущено: <b>{ans.Пропущено в прайсе}</b><br>
{ptitle:}{producer?:linkproducer?(:Общий прайс для всех производителей):com}
{ctitle:}{producer?:linkproducer?(:Общие данные для всех производителей):com}
{linkproducer:}Производитель: <a href="/-showcase/producers/{producer_nick}">{producer}</a>
{itemname:}<b><a href="/-showcase/prices/{name}">{file|:Нет файла}</a></b><br>
{notime:}файл не внесён,
{noans:}раньше не вносился.
{noduration:}Время загрузки не известно,
{itemprice:}
	<div class="d-flex table {mtime>time?:bg-warning} rounded">
		<div class="p-2" style="width:300px">
			{list?:itemname}
			{:ptitle}
			<i></i><br>{size:size} {mtime:time}
		</div>
		<div class="p-2 flex-grow-1">
			{isdata?:icount}
			{duration?duration:duration?:noduration} {time?time:time?:notime} {ans?ans:ans?:noans}
		</div>
		<div class="p-2 text-right" style="width:300px">
			<span class="btn btn-primary" onclick="Action('load','{name}','{conf.prices}{file}')">Внести</span>
			{isdata?:actdelprice}
		</div>
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
		<div class="p-2" style="width:300px">
			<b>{file|:Нет файла}</b><br>
			{:ctitle}<br>
			{size:size} {mtime:time}
		</div>
		<div class="p-2 flex-grow-1">
			{time?:catcount}
			{duration?duration:duration?:noduration} {time?time:time?:notime} {ans?ans:ans?:noans}
		</div>
		{actions?:actions}
	</div>
	{ans:}
	<span class="a" onclick="$(this).next().slideToggle()">{..time??:прошлый }ответ</span>.
	<div style="display:none" class="alert alert-success">{~print(.)}</div>
	{catcount:}Принято: <b>{icount}</b> {~words(icount,:позиция,:позиции,:позиций)}<br>
{actdelprice:}<span class="btn btn-danger" onclick="Action('remove','{name}','{conf.prices}{file}')">Очистить</span>

{actions:}
		<div class="p-2 text-right" style="width:400px">
			
			<!--<span class="btn btn-secondary" onclick="Action('read','{name}','{conf.tables}{file}')">Разобрать</span>-->
			{file?:actfile}
			{time?:actbunch}
			{time?:actdel}
		</div>
	{actbunch:}
		<span class="btn btn-info" onclick="Action('addFiles','{name}','{conf.tables}{file}')">Связать</span>
	{actfile:}
		<span class="btn btn-primary" onclick="Action('load','{name}','{conf.tables}{file}')">Внести</span>
	{actdel:}<span class="btn btn-danger" onclick="Action('remove','{name}','{conf.tables}{file}')">Очистить</span>
{foot:}
	<hr>
	<form id="form" method="POST">
		<input id="formaction" type="hidden" name="action" value="">
		<input id="formsrc" type="hidden" name="src" value="">
		<input id="formname" type="hidden" name="name" value="">
	</form>
	<script>
		Action = function(action, name, src) {
			if (src) formsrc.value = src;
			if (name) formname.value = name;
			formaction.value = action;
			form.submit();
		}
	</script>