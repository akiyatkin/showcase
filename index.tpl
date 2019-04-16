{menu:}
	<a href="/-showcase/catalog">Данные</a> | <a href="/-showcase/prices">Прайсы</a>
	{res:res}
{root:}
	{:menu}
{res:}<div class="mt-2 alert alert-success">{~print(.)}</div>
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
			<big>{producer|:Общий}</big><br>
			<i>{file|:Нет файла}</i><br>{size:size} {mtime:time}
		</div>
		<div class="p-2 flex-grow-1">
			{isdata?:icount}
			{count?:price-count}<br>
			{duration:duration} {time:time}<br>
			{isopt?:Есть опции?:Нет опций}
		</div>
		<div class="p-2" style="width:300px">
			<span class="btn btn-primary" onclick="Action('load','{name}','{conf.catalogsrc}{file}')">Внести</span>
			{isdata?:actdel}
		</div>
		
	</div>
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
		<div class="p-2" style="width:300px">
			<span class="btn btn-primary" onclick="Action('load','{name}','{conf.catalogsrc}{file}')">Внести</span>
			{isdata?:actdel}
		</div>
		
	</div>
	{catalog-count:}В документе <b>{count}</b> {~words(count,:строка,:строки,:строк)} с Артикулом.
{actdel:}<span class="btn btn-danger" onclick="Action('remove','{name}','{conf.catalogsrc}{file}')">Очистить</span>
	
{foot:}
	<hr>
	<div class="d-flex justify-content-between">
		<div>
			<span class="btn btn-info" onclick="Action('addFiles')">Связать с файлами</span>
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