<?php

class DomFinder {
	function __construct($page) {
		$html = file_get_contents($page);
		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = true;
		$doc->resolveExternals = true;
		@$doc->loadHTML($html);
		$this->xpath = new DOMXPath($doc);
		$this->xpath->registerNamespace("html", "http://www.w3.org/1999/xhtml");
	}

	function find($criteria=NULL,$getAttr=FALSE) {
		if ($criteria) {
		  $entries = $this->xpath->query($criteria);
		  $results = array();
			foreach ($entries as $entry) {
				if (!$getAttr) {
					$results[] =  $entry->nodeValue;
				} else {
			    $results[] =  $entry->getAttribute($getAttr);
			  }
			}
			return $results;
		}
		return NULL;
	}
}


class BookRetreiver {
	public $cover = NULL;
	public $isbn = NULL;
	public $year = NULL;
	public $title = NULL;
	public $summary = NULL;
	protected	$base_url = NULL;

	function __construct($url = NULL) {
		if ($url) {
			$dom = new DomFinder($url);
			$isbn = $dom->find("//div[@class='espTec']/p[2]/script");

			if (sizeof($isbn) > 0) {
				$this->isbn = $this->parse_isbn($isbn[0]);
			}

			$year = $dom->find("//div[@class='espTec']/p[7]/text()");
			if (sizeof($year) > 0) {
				$this->year = trim($year[0]);
			}

			$cover = $dom->find("//div[@class='boxImg2']/img",'src');
			if (sizeof($cover) > 0) {
				$this->cover = $this->get_cover($cover[0]);
			}

			$title = $dom->find("//div[@class='detalheEsq2']/h2");
			if (sizeof($title) > 0) {
				$this->title = $this->parse_title($title[0]);
			}

			$summary = $dom->find("//div[@class='resenha']/p");
			if (sizeof($summary) > 0) {
				$this->summary = $summary[0];
			}
		}
		$this->base_url = "http://www.livrariacultura.com.br";
	}

	function get_cover($cover=null) {
	  if ($cover) {
			$cover = str_replace("capas","capas_lg", $cover);
	  }
	  return $cover;
	}

	function parse_isbn($line) {
		$line = str_replace("document.write('","",$line);
		$line = str_replace("');","",$line);
		return trim(html_entity_decode($line));
	}

	function parse_title($title=NULL) {
		if ($title) {
			$title = strtolower($title);
			$tokens = explode(", ",$title);
			if (sizeof($tokens) > 1) {
				// Only reformat words with inverted article title name
				if (strlen($tokens[1]) <= 3) {
					$title = $tokens[1] . " " . $tokens[0];
				}
				$title = ucwords($title);
			}
		}
		return $title;
	}

	function findTopBooks() {
		$books = array();
		$dom = new DomFinder($this->base_url . "/scripts/cultura/maisv/maisv.asp");
		$book_urls = $dom->find("//div[@class='img_capa']/a",'href');
		foreach ($book_urls as $book_url) {
			$book_url = $this->base_url . $book_url;
		  $book = new self($book_url);
		  $books[] = $book;
		}
		return $books;
	}

	function findBookByISBN($isbn = NULL) {
    if ($isbn) {
      $urlcover = 'http://www.livrariacultura.com.br/scripts/busca/busca.asp?';
      $params['avancada'] = 1;
      $params['titem'] = 1;
      $params['palavraISBN'] = $isbn;

      $query_string = http_build_query($params);

      $book = new self($urlcover . $query_string);
      return $book;

    }
    return NULL;
	}
}

$books = new BookRetreiver();
print_r($books->findTopBooks());
