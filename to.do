•	Template the hyperlink, so that the abbreviation <abc.com/…> is theme-specific
•	Have to template the error messages too, still using a class
•	Template date format too

•	Link to go to the last post on a page

•	Put RSS feeds in <head>!
	-	An RSS feed for threads in a folder ordered by updated time?

•	A way to regenerate index.rss, if a thread is deleted, for example (may have to make index.rss dynamic)

•	Show posts from the OP in a different colour

•	Error message if thread already exists (could be paged out of view)
	-	Could be done with an interstitial page, point user to the thread
	-	Also, if file exists, could append a number to flattened title to allow more than one thread of same name

•	Check for double-posting

•	Search:
	
	<form method="get" action="http://google.com/search">
		<input type="hidden" name="as_sitesearch" value="&__HOST__;" />
		<input type="search" name="as_q" />
		<input type="submit" value="Search" />
	</form>

•	Sitemap

•	Post editing
•	Post deleting (problem with IDs reshuffling, need to create permalink IDs)
	-	Could keep post and just remove text "This post has been bahleeted."

•	IE support (no reason why not)

•	Mobile support (iPhone / iPad / Android)

•	Read-only (lock) threads and folders. Use file-permissions?