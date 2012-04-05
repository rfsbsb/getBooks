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

  function find($criteria = NULL, $getAttr = FALSE) {
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

  function count($criteria = NULL) {
    $items = 0;
    if ($criteria) {
      $entries = $this->xpath->query($criteria);
      foreach ($entries as $entry) {
        $items++;
      }
    }
    return $items;
  }

}


class Book {
  public $cover = NULL;
  public $isbn = NULL;
  public $year = NULL;
  public $title = NULL;
  public $sub_title = NULL;
  public $summary = NULL;
  public $author = NULL;
  public $pages = NULL;

  function __construct($dom = NULL) {
    if ($dom) {
      $isbn = $dom->find("//div[@class='espTec']/p[2]/script");
      if (sizeof($isbn) > 0) {
        $this->isbn = $this->parse_isbn($isbn[0]);
      }

      // year and page count may vary, we get the correct indexes
      $number_items = $dom->count("//div[@class='espTec']/p");
      $year_index = $number_items -1;
      $pages_index = $number_items;

      $year = $dom->find("//div[@class='espTec']/p[".$year_index."]/text()");
      if (sizeof($year) > 0) {
        $this->year = trim($year[0]);
      }

      $pages = $dom->find("//div[@class='espTec']/p[".$pages_index."]/text()");
      if (sizeof($pages) > 0) {
        $this->pages = trim($pages[0]);
      }

      $cover = $dom->find("//div[@class='boxImg2']/img",'src');
      if (sizeof($cover) > 0) {
        $this->cover = $this->get_cover($cover[0]);
      }

      $title = $dom->find("//div[@class='detalheEsq2']/h2[contains(@class, 'resenha')]");
      if (sizeof($title) > 0) {
        $this->title = $this->parse_title($title[0]);
      }

      $sub_title = $dom->find("//div[@class='detalheEsq2']/h2[contains(@class, 'sub_resenha')]");
      if (sizeof($sub_title) > 0) {
        $this->sub_title = $this->parse_title($sub_title[0]);
      }

      $summary = $dom->find("//div[@class='resenha']/p");
      if (sizeof($summary) > 0) {
        $this->summary = $summary[0];
      }

      $author = $dom->find("//div[@class='detalheEsq2']/p[2]/a");
      if (sizeof($author) > 0) {
        $this->author = $this->parse_author($author[0]);
      }

    }
  }

  function get_cover($cover = null) {
    if ($cover) {
      $cover = str_replace("capas","capas_lg", $cover);
    }
    return $cover;
  }

  function parse_isbn($isbn) {
    $isbn = str_replace("document.write('", "", $isbn);
    $isbn = str_replace("');", "", $isbn);
    return trim(html_entity_decode($isbn));
  }

  function parse_title($title = NULL) {
    if ($title) {
      $title = strtolower($title);
      $tokens = explode(", ", $title);
      if (sizeof($tokens) > 1) {
        // Only reformat words with inverted article title name
        if (strlen($tokens[1]) <= 3) {
          $title = $tokens[1] . " " . $tokens[0];
        }
        // If title has a subtitle treat it accordingly
        if (strstr($tokens[1], ' - ')) {
          $subtitle_tokens = explode(" - ", $tokens[1]);
          $title = $subtitle_tokens[0] . " " . $tokens[0] . " - " . $subtitle_tokens[1];
        }
      }
      $title = ucwords($title);
    }
    return $title;
  }

  function parse_author($author = NULL) {
    if ($author) {
      $author = strtolower($author);
      $tokens = explode(", ", $author);
      // Only reformat if there is a comma
      if (sizeof($tokens) > 1) {
        $author = $tokens[1] . " " . $tokens[0];
      }
      $author = ucwords($author);
    }
    return $author;
  }

}


class BookRetreiver {
  protected $base_url = "http://www.livrariacultura.com.br";

  function findTopBooks() {
    $books = array();
    $dom = new DomFinder($this->base_url . "/scripts/cultura/maisv/maisv.asp");
    $book_urls = $dom->find("//div[@class='img_capa']/a", 'href');
    foreach ($book_urls as $book_url) {
      $book_dom = new DomFinder($this->base_url . $book_url);
      $book = new Book($book_dom);
      $books[] = $book;
    }
    return $books;
  }

  function findBookByISBN($isbn = NULL) {
    if ($isbn) {
      $search_url = $this->base_url . '/scripts/busca/busca.asp?';
      $params['avancada'] = 1;
      $params['titem'] = 1;
      $params['palavraISBN'] = $isbn;

      $query_string = http_build_query($params);
      $book_dom = new DomFinder($search_url . $query_string);
      $book = new Book($book_dom);
      return $book;

    }
    return NULL;
  }
}

$books = new BookRetreiver();
print_r($books->findbookByISBN('9788511150285'));
