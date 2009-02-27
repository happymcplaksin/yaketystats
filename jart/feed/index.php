<?php
try {
	include('FeedGenerator.php');
	$feeds=new FeedGenerator;
	$feeds->setGenerator(new RSSGenerator); # or AtomGenerator
	$feeds->setAuthor('mail@example.com');
	$feeds->setTitle('Example Site');
	$feeds->setChannelLink('http://example.com/rss/');
	$feeds->setLink('http://example.com');
	$feeds->setDescription('Description of channel');
	$feeds->setID('http://example.com/rss/');

	$feeds->addItem(new FeedItem('http://example.com/news/1', 'Example news', 'http://example.com/news/1', '<p>Description of news</p>'));
	$feeds->addItem(new FeedItem('http://example.com/news/2', 'Example news', 'http://example.com/news/2', '<p>Description of news</p>'));

	$feeds->display();
}
catch(FeedGeneratorException $e){
	echo 'Error: '.$e->getMessage();
}
?>
