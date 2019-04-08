{menu:}
	<a href="/-showcase/catalog">Данные</a> | <a href="/-showcase/prices">Прайсы</a>
	{res:res}
{root:}
	{:menu}
{res:}<div class="mt-2 alert alert-success">{~print(.)}</div>
{PRICES:}
	{:menu}
	<h1>Прайсы</h1>
	{list::item}
	{:foot}
{CATALOG:}
	{:menu}
	<h1>Данные</h1>
	{list::item}
	{:foot}
{item:}
	{:itemrow}
	<span class="btn btn-primary" onclick="Action('load','{name}','{conf.catalogsrc}{file}')">Внести</span>
	{isdata?:actdel}
	<!--<span class="btn btn-info" onclick="Action('info','{name}','{conf.catalogsrc}{file}')">Подробней</span>-->
	{:/itemrow}
{actdel:}<span class="btn btn-danger" onclick="Action('remove','{name}','{conf.catalogsrc}{file}')">Очистить</span>
{itemrow:}
	<div class="d-flex table {mtime>time?:bg-warning} rounded">
		<div class="p-2" style="width:300px">
			<big>{producer|:Общий}</big><br>
			<i>{file|:Нет файла}</i><br>{size:size} {mtime:time}
		</div>
		<div class="p-2 flex-grow-1">
			{isdata?:icount}
			{count?:count}<br>
			{duration:duration} {time:time}<br>
			{isopt?:Есть опции}
		</div>
		<div class="p-2" style="width:300px">
			{/itemrow:}
		</div>
		
	</div>
	{time:}<b title="{~date(:H:i,.)}">{~date(:d.m,.)}</b>
	{size:}<b title="Примерно {durationrate} Кб в секунду = {~multi(.,durationfactor)} сек">{.}</b> Кб, 
	{duration:}Обработка <b>{.}</b> сек,
	{icount:}Принято <b>{icount}</b> {~words(icount,:позиций,:позиции,:позиций)}.<br>
	{count:}В документе <b>{count}</b> {~words(count,:позиций,:позиции,:позиций)}.
{foot:}
	<hr>
	<div class="text-right">
		<span class="btn btn-danger" onclick="Action('clearAll')">Очистить всё</span>
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