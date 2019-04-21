{menu:}
	{(:/-showcase/api/search):link}Список{:/link} |
	{(:/-showcase/api/pos):link}Позиция{:/link}
	{res:res}
	{link:}<a class="{location.pathname=.?:font-weight-bold}" href="{.}">{/link:}</a>
{API:}
	{:menu}
	<p>Всего в каталоге <b>{count} {~words(count,:модель,:модели,:моделей)}</b>.