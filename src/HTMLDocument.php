<?php

declare(strict_types=1);

namespace Tivins\Webapp;

class HTMLDocument
{
    private string $lang = 'en';
    private string $title;
    private string $body;
    private array $stylesheets = [];
    private array $jsFiles = [];
    private string $css = '';
    private array $meta = [];

    /**
     * Crée un nouveau document HTML.
     *
     * Ajoute automatiquement une balise meta viewport pour la responsivité.
     *
     * @param string $title Le titre du document (balise <title>)
     * @param string $body Le contenu HTML du corps (balise <body>)
     *
     * @example
     * ```php
     * $doc = new HTMLDocument('Mon Site', '<h1>Bienvenue</h1>');
     * echo $doc->render();
     * ```
     */
    public function __construct(string $title, string $body = '')
    {
        $this->title = $title;
        $this->body = $body;
        $this->addMeta('viewport', 'width=device-width, initial-scale=1');
    }

    /**
     * Ajoute un fichier JavaScript au document.
     *
     * @param string $file Le chemin vers le fichier JavaScript
     * @param string $type Le type de script ('module', 'text/javascript', etc.)
     * @return static Instance courante pour le chaînage de méthodes
     *
     * @example
     * ```php
     * $doc->addJavaScript('/js/app.js', 'module')
     *     ->addJavaScript('/js/legacy.js', 'text/javascript');
     * ```
     */
    public function addJavaScript(string $file, string $type = 'module'): static
    {
        $this->jsFiles[] = [$type, $file];
        return $this;
    }

    /**
     * Ajoute une feuille de style CSS au document.
     *
     * @param string $file Le chemin vers le fichier CSS
     * @return static Instance courante pour le chaînage de méthodes
     *
     * @example
     * ```php
     * $doc->addStylesheet('/css/style.css')
     *     ->addStylesheet('/css/theme.css');
     * ```
     */
    public function addStylesheet(string $file): static
    {
        $this->stylesheets[] = $file;
        return $this;
    }

    /**
     * Définit le contenu du corps du document.
     *
     * @param string $body Le contenu HTML du corps
     * @return static Instance courante pour le chaînage de méthodes
     *
     * @example
     * ```php
     * $doc->setBody('<div class="container">Contenu</div>');
     * ```
     */
    public function setBody(string $body): static
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Ajoute du CSS inline au document.
     *
     * Le CSS est ajouté dans une balise <style> dans le <head>.
     *
     * @param string $cssString Le code CSS à ajouter
     * @return static Instance courante pour le chaînage de méthodes
     *
     * @example
     * ```php
     * $doc->addStyle('body { margin: 0; padding: 0; }');
     * ```
     */
    public function addStyle(string $cssString): static
    {
        $this->css .= $cssString;
        return $this;
    }

    /**
     * Ajoute une balise meta au document.
     *
     * @param string $name Le nom de la balise meta
     * @param string $content Le contenu de la balise meta
     * @return static Instance courante pour le chaînage de méthodes
     *
     * @example
     * ```php
     * $doc->addMeta('description', 'Description de la page')
     *     ->addMeta('keywords', 'php, web, framework');
     * ```
     */
    public function addMeta(string $name, string $content): static
    {
        $this->meta[$name] = $content;
        return $this;
    }

    /**
     * Supprime une balise meta du document.
     *
     * @param string $name Le nom de la balise meta à supprimer
     * @return static Instance courante pour le chaînage de méthodes
     *
     * @example
     * ```php
     * $doc->removeMeta('keywords');
     * ```
     */
    public function removeMeta(string $name): static
    {
        unset($this->meta[$name]);
        return $this;
    }

    /**
     * Génère le code HTML complet du document.
     *
     * @return string Le code HTML complet avec toutes les balises, styles et scripts
     *
     * @example
     * ```php
     * $html = $doc->render();
     * file_put_contents('output.html', $html);
     * ```
     */
    public function render(): string
    {
        $css = implode(array_map(
            fn(string $file) => '<link rel="stylesheet" type="text/css" href="' . htmlspecialchars($file, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">',
            $this->stylesheets
        ));
        $js = implode(array_map(
            fn(array $data) => '<script type="' . htmlspecialchars($data[0], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" src="' . htmlspecialchars($data[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"></script>',
            $this->jsFiles
        ));
        $meta = implode(array_map(
            fn(string $name, string $content) => '<meta name="' . htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" content="' . htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">',
            array_keys($this->meta),
            $this->meta
        ));
        return '<!DOCTYPE HTML><html lang="' . htmlspecialchars($this->lang, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">'
            . '<head>'
            . '<title>' . htmlentities($this->title, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</title>'
            . '<meta charset="utf-8">'
            . $meta
            . $css
            . ($this->css ? '<style>' . $this->css . '</style>' : '')
            . $js
            . '</head><body>' . $this->body . '</body></html>';
    }
}