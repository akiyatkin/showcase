{menu:}
	{res:res}
	{link:}<a class="{location.pathname=.?:font-weight-bold}" href="{.}">{/link:}</a>
{API:}
	{:menu}
	<p>Всего в каталоге <b>{count} {~words(count,:модель,:модели,:моделей)}</b>.</p>
	<a href="/-showcase/api/search/?m=:more.Наличие-на-складе.Акция=1:more.Наличие-на-складе.Распродажа=1">Акционные товары и распродажа</a><br>
	<a href="/-showcase/api/producers">Производители</a><br>
	<a href="/-showcase/api/groups">Группы</a><br>
	<a href="/-showcase/api/search">Список позиций</a><br>
	<a href="/-showcase/api/pos/producer_nick/article_nick">Позиция</a><br>