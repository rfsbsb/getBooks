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
      $book_id = $this->parse_book_id($dom->find("//link[@rel='canonical']",'href'));

      $isbn13 = $dom->find("//span[@id='Conteudo_PainelEventoDescricao_PainelEspecificacaoTecnica1_ContIsbn13']");
      $isbn10 = $dom->find("//span[@id='Conteudo_PainelEventoDescricao_PainelEspecificacaoTecnica1_ContIsbn']");
      if (sizeof($isbn13) > 0) {
        $this->isbn = trim($isbn13[0]);
      } elseif (sizeof($isbn10) > 0) {
        $this->isbn = trim($isbn10[0]);
      }

      $language = $dom->find("//span[@id='Conteudo_PainelEventoDescricao_PainelEspecificacaoTecnica1_ContIdioma']");
      if (sizeof($language) > 0) {
        $this->language = ucfirst(strtolower(trim($language[0])));
      }

      $year = $dom->find("//span[@id='Conteudo_PainelEventoDescricao_PainelEspecificacaoTecnica1_ContAnoLancamento']");
      if (sizeof($year) > 0) {
        $year = trim($year[0]);
        if (is_numeric($year)) {
          $this->year = $year;
        }
      }

      $pages = $dom->find("//span[@id='Conteudo_PainelEventoDescricao_PainelEspecificacaoTecnica1_ContNumeroPaginas']");
      if (sizeof($pages) > 0) {
        $pages = trim($pages[0]);
        if (is_numeric($pages)) {
          $this->pages = $pages;
        }
      }

      if ($book_id) {
        $this->cover = $this->get_cover($book_id);
      }

      $title = $dom->find("//h1[@id='Conteudo_PainelEventoInformacao_Titulo_Resenha']");
      if (sizeof($title) > 0) {
        $this->title = $this->parse_title($title[0]);
      }

      $sub_title = $dom->find("//h2[@id='Conteudo_PainelEventoInformacao_SubTitulo_Resenha']");
      if (sizeof($sub_title) > 0) {
        $this->sub_title = $this->parse_title($sub_title[0]);
      }

      $summary = $dom->find("//div[@id='Conteudo_PainelEventoDescricao_PainelSinopseSobreAutor1_contSinopse']");
      if (sizeof($summary) > 0) {
        $this->summary = trim($summary[0]);
      }

      $authors = $dom->find("//div[@class='detalheEsq2']/p");
      $authors_names = $this->get_authors_names($authors);
      $this->authors = $this->parse_authors($authors_names);
    }
  }

  function parse_book_id($link) {
    $url = $link[0];
    if (preg_match('/\/Produto\/LIVRO\/[A-Z0-9-_]*\/([0-9]*)/', $url, $matches)) {
      return $matches[1];
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
          $author = $tokens[1] . " " . $tokens[0];
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
      $opts = array('http' => array('max_redirects'=>1, 'ignore_errors'=>1));
      stream_context_get_default($opts);
      $headers = get_headers($url, true);
      if (isset($headers['Location']) && preg_match('/nitem=([0-9]*)&/', $headers['Location'], $matches) ) {
        return $this->base_url . "/Produto/LIVRO/" . $matches[1];
      }
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
      $search_url = $this->base_url . '/scripts/busca/busca.asp?';
      $params['avancada'] = 1;
      $params['titem'] = 1;
      $params['palavraISBN'] = $isbn;

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
