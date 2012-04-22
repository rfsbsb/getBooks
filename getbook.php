<?php

class DomFinder {
  function __construct($page) {
    $html = @file_get_contents($page);
    $doc = new DOMDocument();
    $this->xpath = null;
    if ($html) {
      $doc->preserveWhiteSpace = true;
      $doc->resolveExternals = true;
      @$doc->loadHTML($html);
      $this->xpath = new DOMXPath($doc);
      $this->xpath->registerNamespace("html", "http://www.w3.org/1999/xhtml");
    }
  }

  function find($criteria = NULL, $getAttr = FALSE) {
    if ($criteria && $this->xpath) {
      $entries = $this->xpath->query($criteria);
      $results = array();
      foreach ($entries as $entry) {
        if (!$getAttr) {
          $results[] = $entry->nodeValue;
        } else {
          $results[] = $entry->getAttribute($getAttr);
        }
      }
      return $results;
    }
    return NULL;
  }

  function count($criteria = NULL) {
    $items = 0;
    if ($criteria && $this->xpath) {
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
  public $language = NULL;
  public $year = NULL;
  public $title = NULL;
  public $sub_title = NULL;
  public $summary = NULL;
  public $authors = array();
  public $pages = NULL;

  function __construct($dom = NULL) {
    if ($dom) {
      $isbn = $dom->find("//div[@class='espTec']/p[2]/script");
      if (sizeof($isbn) > 0) {
        $this->isbn = $this->parse_isbn($isbn[0]);
      }

      $language = $dom->find("//div[@class='espTec']/p[3]/text()");
      if (sizeof($language) > 0) {
        $this->language = ucfirst(trim($language[0]));
      }

      // year and page count may vary, we get the correct indexes
      $number_items = $dom->count("//div[@class='espTec']/p");
      $year_index = $number_items -1;
      $pages_index = $number_items;

      $year = $dom->find("//div[@class='espTec']/p[".$year_index."]/text()");
      if (sizeof($year) > 0) {
        $year = trim($year[0]);
        if (is_numeric($year)) {
          $this->year = $year;
        }
      }

      $pages = $dom->find("//div[@class='espTec']/p[".$pages_index."]/text()");
      if (sizeof($pages) > 0) {
        $pages = trim($pages[0]);
        if (is_numeric($pages)) {
          $this->pages = $pages;
        }
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

      $authors = $dom->find("//div[@class='detalheEsq2']");
      $authors_names = $this->get_authors($authors) ;
      $this->authors = $this->parse_authors($authors_names);
    }
  }

  function get_authors($authors) {
    if (sizeof($authors) > 0) {
      preg_match_all("/Autor:(.*)/", $authors[0], $names);
      return $names[1];
    }
    return null;
  }

  function get_cover($cover_orig = null) {
    if ($cover_orig) {
      $cover = str_replace("capas","capas_lg", $cover_orig);
      if (!@file_get_contents($cover,0,null,0,1)) {
        $cover = null;
      }
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
      $title = strtr(strtolower($title),"ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÜÚÞß","àáâãäåæçèéêëìíîïðñòóôõö÷øùüúþÿ");
	  $title = str_replace("(livro de bolso)", "", $title);
      $tokens = explode(", ", $title);
      if (sizeof($tokens) > 1) {
        // there are too many commas, which means, many subtitles
        if (sizeof($tokens) > 2) {
          $title = $tokens[2];
        } else {
          $tokens[1] = preg_replace("/(v\.[0-9]*)( - )?(.*)/", "$3$2", $tokens[1]);
          // Only reformat words with inverted article title name
          if (strlen($tokens[1]) <= 3) {
            $title = $tokens[1] . " " . $tokens[0];
          }
          // If title has a subtitle treat it accordingly
          if (strstr($tokens[1], ' - ')) {
            $subtitle_tokens = explode(" - ", $tokens[1]);
            if (trim($subtitle_tokens[1])) {
              $title = $subtitle_tokens[0] . " " . $tokens[0] . " - " . $subtitle_tokens[1];
            } else {
              $title = $subtitle_tokens[0] . " - " . $tokens[0];
            }
          }
        }
      }
      $title = trim(preg_replace("/v\.[0-9]*( - )?(.*)/", "$2", $title));
      $title = ucwords($title);
    }
    return $title;
  }

  function parse_authors($authors = array()) {
    if (sizeof($authors) > 0) {
      foreach ($authors as $key => $author) {
        $author = trim(strtr(strtolower($author),"ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÜÚÞß","àáâãäåæçèéêëìíîïðñòóôõö÷øùüúþÿ"));
        $tokens = explode(", ", $author);
        // Only reformat if there is a comma
        if (sizeof($tokens) > 1) {
          $author = $tokens[1] . " " . $tokens[0];
        }
        $authors[$key] = ucwords($author);
      }
    }
    return $authors;
  }

}


class BookRetreiver {
  protected $base_url = "http://www.livrariacultura.com.br";

  function findTopBooks() {
    $books = array();
    $dom = new DomFinder($this->base_url . "/scripts/cultura/home/lancamentos.asp?titem=1");
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
      if (!empty($book->isbn)) {
        return $book;
      }
    }
    return NULL;
  }
}
