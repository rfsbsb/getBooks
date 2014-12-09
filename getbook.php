<?php

class DomFinder {

  function file_get_contents_utf8($fn) {
    $content = file_get_contents($fn);
    return mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8");
  }

  function __construct($page) {
    $html = @$this->file_get_contents_utf8($page);
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
  private $dom;

  function __construct($dom = NULL) {
    if ($dom) {
      $this->dom = $dom;
      $this->parse_general_data();

      $book_id = $this->parse_book_id($dom->find("//link[@rel='canonical']",'href'));

      if ($book_id) {
        $this->cover = $this->get_cover($book_id);
      }

      $title = $dom->find("//title");

      if (sizeof($title) > 0) {
        $this->title = $this->parse_title($title[0]);
      }

      /*$sub_title = $dom->find("//h2[@id='Conteudo_PainelEventoInformacao_SubTitulo_Resenha']");
      if (sizeof($sub_title) > 0) {
        $this->sub_title = $this->parse_title($sub_title[0]);
      }*/

      $summary = $dom->find("//meta[@name='description']", 'content');
      if (sizeof($summary) > 0) {
        $this->summary = trim($summary[0]);
      }

      $authors = $dom->find("//section[@class='description']/ul[@class='info']/li");
      $authors_names = $this->get_authors_names($authors);
      $this->authors = $this->parse_authors($authors_names);
    }
  }

  function parse_general_data() {

    $data = $this->dom->find("//section[@id='product-details']//div/ul/li");

    foreach ($data as $key => $value) {
      $value = trim($value);
      $parts = explode(":", $value);
      $parts[1] = preg_replace('/[\x{C2}\n+\r+\t+\s+]/u', '', $parts[1]);
      $parts[1] = strtr(strtolower($parts[1]),"ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÜÚÞß","àáâãäåæçèéêëìíîïðñòóôõö÷øùüúþÿ");
      switch (trim($parts[0])) {
        case 'Idioma':
          $this->language = ucfirst(strtolower($parts[1]));
          break;
        case 'Ano':
          $this->year = $parts[1];
          break;
        case 'Código de Barras':
          $this->isbn = $parts[1];
          break;
        case "Nº de Páginas":
          $this->pages = $parts[1];
          break;
      }
    }
  }

  function parse_book_id($link) {
    $url = $link[0];
    $parts = explode("-", $url);
    $last = $parts[count($parts)-1];
    if (is_numeric($last)) {
      return $last;
    }
    return false;
  }

  function parse_isbn($dom) {
      $isbn13 = $dom->find("//section[@id='product-details']/div/ul/li[9]/text()");
      $isbn10 = $dom->find("//section[@id='product-details']/div/ul/li[10]/text()");
      $isbn13 = isset($isbn13[1]) ? trim(preg_replace("/([^0-9])/", "$2", $isbn13[1])) : false;
      $isbn10 = isset($isbn10[1]) ? trim(preg_replace("/([^0-9])/", "$2", $isbn10[1])) : false;

      if (strlen($isbn13) > 0) {
       return $isbn13;
      } elseif (strlen($isbn10) > 0) {
        return $isbn10;
      }
      return false;
  }

  function get_authors_names($authors) {
    if (sizeof($authors) > 0) {
      $authors_names = array();
      foreach ($authors as $author) {
        if (preg_match("/Autor:(.*)/", $author, $names)) {
          $authors_names[] = trim($names[1]);
        }
      }
      return $authors_names;
    }
    return null;
  }

  function get_cover($book_id) {
    # TODO: find a way to get the correct CDN. It's working now, but should stop
    $base_url_cover = "http://cdn.b5e8.upx.net.br/imagens/imagem/capas_lg/";
    $base_dir = substr($book_id, -3, 3);
    $cover = $base_url_cover . $base_dir . "/". $book_id . ".jpg";
    $headers = get_headers($cover, true);
    if (strstr($headers[0], '404')) {
      $cover = null;
    }
    return $cover;
  }

  function parse_title($title = NULL) {
    if ($title) {
      $title = strtr(strtolower($title),"ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÜÚÞß","àáâãäåæçèéêëìíîïðñòóôõö÷øùüúþÿ");
      // removing useless information
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
        $author = strtr(strtolower($author),"ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÜÚÞß","àáâãäåæçèéêëìíîïðñòóôõö÷øùüúþÿ");
        $author = trim(preg_replace("/([0-9\-()]*)/", "", $author));
        $tokens = explode(", ", $author);
        // Only reformat if there is a comma
        if (sizeof($tokens) > 1) {
          $author = $tokens[1] . $tokens[0];
        }
        $authors[$key] = ucwords($author);
      }
    }
    return $authors;
  }

}


class BookRetriever {
  protected $base_url = "http://www.livrariacultura.com.br";

  function getBookUrl($url = null) {
    if ($url) {
      $dom = new DomFinder($url);
      $book_url = $dom->find("//div[@class='small-slider-content']/a", 'href');
      return $book_url[0];
    }
    return false;
  }

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
      $search_url = $this->base_url . '/busca?';
      $params['N'] = 0;
      $params['Ntt'] = $isbn;

      $query_string = http_build_query($params);
      $url = $this->getBookUrl($search_url . $query_string);
      $book_dom = new DomFinder($url);
      $book = new Book($book_dom);
      if (!empty($book->isbn)) {
        return $book;
      }
    }
    return NULL;
  }
}

$b = new BookRetriever();
print_r($b->findBookByISBN('9780756410575'));
