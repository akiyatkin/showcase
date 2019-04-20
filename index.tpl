{menu:}
	{(:/-showcase/catalog):link}Данные{:/link} |
	{(:/-showcase/prices):link}Прайсы{:/link} |
	{(:/-showcase/groups):link}Группы{:/link} |
	{(:/-showcase/producers):link}Производители{:/link} |
	{(:/-showcase/models):link}Модели{:/link}  |
	{(:/-showcase/api):link}API{:/link}
	<span class="float-right">Загрузить с Яндекс Диска
	<a href="/-showcase/prices?-ydisk=tables">Данные</a> | 
	<a href="/-showcase/prices?-ydisk=prices">Прайсы</a> 
	</span>
	{res:res}
	{link:}<a class="{location.pathname=.?:font-weight-bold}" href="{.}">{/link:}</a>
{root:}
	{:menu}
	<p>Всего в каталоге <b>{count} {~words(count,:модель,:модели,:моделей)}</b>.
{res:}<div class="mt-2 alert alert-success">{~print(.)}</div>
{MODELS:}
	{:menu}
	<h1>Модели</h1>
	{list::model}
	{model:}
		<div class="mb-2" style="clear:both">
			<span title="Позиций {count}">{producer_nick} {article_nick}</span>{img?:pic}
			<div><small>{Цена?:cost} <i>{group}</i> {catalog}.xlsx</small></div>
		</div>
	{pic:} <small class="a" onclick="$(this).addClass('float-right').removeClass('a').html('<img src=\'/-imager/?src={img}&w=100\'>&nbsp;')">image</small>
	{cost:}{~cost(Цена)} руб.
{PRODUCERS:}
	{:menu}
	<h1>Производители</h1>
	{list::producer}
	{producer:}{producer} <small><b>{count}</b> {catalog}.xlsx</small><br>
{GROUPS:}
	{:menu}
	<h1>Группы</h1>
	{list.childs::groups}
	<br><br><br><br>
	{groups:}
		{group} <small><b>{count}</b> {catalog}.xlsx</small>
		<div class="ml-4">{childs::groups}</div>
{PRICES:}
	{:menu}
	<h1>Прайсы</h1>
	{list::itemprice}
	{:foot}
{CATALOG:}
	{:menu}
	<h1>Данные</h1>
	{list::itemcatalog}
	{:foot}
{time:}<b title="{~date(:H:i,.)}">{~date(:d.m,.)}</b>
{size:}<b title="Примерно {durationrate} Кб в секунду = {~multi(.,durationfactor)} сек">{.}</b> Кб, 
{duration:}Обработка <b>{.}</b> сек,
{icount:}Принято <b>{icount}</b> {~words(icount,:позиций,:позиции,:позиций)}.<br>
{itemprice:}
	<div class="d-flex table {mtime>time?:bg-warning} rounded">
		<div class="p-2" style="width:300px">
			<big>{producer|:com}</big><br>
			<i>{file|:Нет файла}</i><br>{size:size} {mtime:time}
		</div>
		<div class="p-2 flex-grow-1">
			{isdata?:icount}
			{count?:price-count}<br>
			{duration:duration} {time:time}<br>
			{isopt?:showopt?:Нет опций}
		</div>
		<div class="p-2 text-right" style="width:300px">
			<span class="btn btn-primary" onclick="Action('load','{name}','{conf.pricessrc}{file}')">Внести</span>
			{isdata?:actdel}
		</div>
		
	</div>
	{showopt:}
	<span class="a" onclick="$(this).next().slideToggle()">Есть опции</span>
	<div style="display:none">
	<b>Синонимы</b> {~print(synonyms)}
	<b>Параметры</b> {~print(props)}
	</div>
	{com:}<b class="text-danger">Общий</b>
	{price-count:}В документе <b>{count}</b> {~words(count,:строка,:строки,:строк)} с ключём прайса <b>{priceprop}</b>.
{itemcatalog:}
	<div class="d-flex table {mtime>time?:bg-warning} rounded">
		<div class="p-2" style="width:300px">
			<big>{producer|:Общий}</big><br>
			<i>{file|:Нет файла}</i><br>{size:size} {mtime:time}
		</div>
		<div class="p-2 flex-grow-1">
			{isdata?:icount}
			{count?:catalog-count}<br>
			{duration:duration} {time:time}<br>
			{isopt?:Есть опции?:Нет опций}
		</div>
		{actions?:actions}
	</div>
	{catalog-count:}В документе <b>{count}</b> {~words(count,:строка,:строки,:строк)} с Артикулом.
{actdel:}<span class="btn btn-danger" onclick="Action('remove','{name}','{conf.catalogsrc}{file}')">Очистить</span>
{actions:}
		<div class="p-2 text-right" style="width:300px">
			<span class="btn btn-primary" onclick="Action('load','{name}','{conf.catalogsrc}{file}')">Внести</span>
			{isdata?:actdel}
		</div>
{foot:}
	<hr>
	<div class="d-flex justify-content-between">
		<div>
			<a href="/-showcase/update" class="btn btn-primary">Внести все новые данные и прайсы</a>
			<!--<span class="btn btn-info" onclick="Action('addFiles')">Связать с файлами</span>-->
			
		</div>
		<div>
			<span class="btn btn-danger" onclick="Action('clearAll')">Очистить все данные и прайсы</span>
		</div>
	</div>
	<hr>
	{~print(options)}
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