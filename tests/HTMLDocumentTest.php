<?php

declare(strict_types=1);

namespace Tivins\WebappTests;

use PHPUnit\Framework\TestCase;
use Tivins\Webapp\HTMLDocument;

class HTMLDocumentTest extends TestCase
{
    public function testConstructorSetsTitle(): void
    {
        $doc = new HTMLDocument('Test Title');
        $html = $doc->render();

        self::assertStringContainsString('<title>Test Title</title>', $html);
    }

    public function testConstructorSetsBody(): void
    {
        $doc = new HTMLDocument('Title', '<div>Body content</div>');
        $html = $doc->render();

        self::assertStringContainsString('<body><div>Body content</div></body>', $html);
    }

    public function testConstructorSetsDefaultViewportMeta(): void
    {
        $doc = new HTMLDocument('Title');
        $html = $doc->render();

        self::assertStringContainsString('<meta name="viewport" content="width=device-width, initial-scale=1">', $html);
    }

    public function testTitleIsHtmlEncoded(): void
    {
        $doc = new HTMLDocument('Title <script>alert("xss")</script>');
        $html = $doc->render();

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert', $html);
    }

    public function testAddJavaScriptReturnsSelf(): void
    {
        $doc = new HTMLDocument('Title');
        $result = $doc->addJavaScript('/js/app.js');

        self::assertSame($doc, $result);
    }

    public function testAddJavaScriptWithDefaultType(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addJavaScript('/js/app.js');
        $html = $doc->render();

        self::assertStringContainsString('<script type="module" src="/js/app.js"></script>', $html);
    }

    public function testAddJavaScriptWithCustomType(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addJavaScript('/js/legacy.js', 'text/javascript');
        $html = $doc->render();

        self::assertStringContainsString('<script type="text/javascript" src="/js/legacy.js"></script>', $html);
    }

    public function testAddMultipleJavaScriptFiles(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addJavaScript('/js/app.js')
            ->addJavaScript('/js/utils.js');
        $html = $doc->render();

        self::assertStringContainsString('<script type="module" src="/js/app.js"></script>', $html);
        self::assertStringContainsString('<script type="module" src="/js/utils.js"></script>', $html);
    }

    public function testAddStylesheetReturnsSelf(): void
    {
        $doc = new HTMLDocument('Title');
        $result = $doc->addStylesheet('/css/style.css');

        self::assertSame($doc, $result);
    }

    public function testAddStylesheet(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addStylesheet('/css/style.css');
        $html = $doc->render();

        self::assertStringContainsString('<link rel="stylesheet" type="text/css" href="/css/style.css">', $html);
    }

    public function testAddMultipleStylesheets(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addStylesheet('/css/reset.css')
            ->addStylesheet('/css/style.css');
        $html = $doc->render();

        self::assertStringContainsString('<link rel="stylesheet" type="text/css" href="/css/reset.css">', $html);
        self::assertStringContainsString('<link rel="stylesheet" type="text/css" href="/css/style.css">', $html);
    }

    public function testSetBodyReturnsSelf(): void
    {
        $doc = new HTMLDocument('Title');
        $result = $doc->setBody('<p>Content</p>');

        self::assertSame($doc, $result);
    }

    public function testSetBodyReplacesExistingBody(): void
    {
        $doc = new HTMLDocument('Title', '<p>Old content</p>');
        $doc->setBody('<p>New content</p>');
        $html = $doc->render();

        self::assertStringNotContainsString('Old content', $html);
        self::assertStringContainsString('<body><p>New content</p></body>', $html);
    }

    public function testAddStyleReturnsSelf(): void
    {
        $doc = new HTMLDocument('Title');
        $result = $doc->addStyle('body { margin: 0; }');

        self::assertSame($doc, $result);
    }

    public function testAddStyle(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addStyle('body { margin: 0; }');
        $html = $doc->render();

        self::assertStringContainsString('<style>body { margin: 0; }</style>', $html);
    }

    public function testAddStyleAccumulates(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addStyle('body { margin: 0; }')
            ->addStyle('p { color: red; }');
        $html = $doc->render();

        self::assertStringContainsString('<style>body { margin: 0; }p { color: red; }</style>', $html);
    }

    public function testNoStyleTagWhenEmpty(): void
    {
        $doc = new HTMLDocument('Title');
        $html = $doc->render();

        self::assertStringNotContainsString('<style>', $html);
    }

    public function testAddMetaReturnsSelf(): void
    {
        $doc = new HTMLDocument('Title');
        $result = $doc->addMeta('description', 'A test page');

        self::assertSame($doc, $result);
    }

    public function testAddMeta(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addMeta('description', 'A test page');
        $html = $doc->render();

        self::assertStringContainsString('<meta name="description" content="A test page">', $html);
    }

    public function testAddMetaOverwritesSameName(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addMeta('description', 'First description')
            ->addMeta('description', 'Second description');
        $html = $doc->render();

        self::assertStringNotContainsString('First description', $html);
        self::assertStringContainsString('<meta name="description" content="Second description">', $html);
    }

    public function testRemoveMetaReturnsSelf(): void
    {
        $doc = new HTMLDocument('Title');
        $result = $doc->removeMeta('viewport');

        self::assertSame($doc, $result);
    }

    public function testRemoveMeta(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->removeMeta('viewport');
        $html = $doc->render();

        self::assertStringNotContainsString('viewport', $html);
    }

    public function testRemoveMetaNonExistent(): void
    {
        $doc = new HTMLDocument('Title');
        // Ne doit pas générer d'erreur
        $doc->removeMeta('nonexistent');
        $html = $doc->render();

        self::assertStringContainsString('<title>Title</title>', $html);
    }

    public function testRenderReturnsValidHtml5Document(): void
    {
        $doc = new HTMLDocument('Title');
        $html = $doc->render();

        self::assertStringStartsWith('<!DOCTYPE HTML>', $html);
        self::assertStringContainsString('<html lang="en">', $html);
        self::assertStringContainsString('<meta charset="utf-8">', $html);
        self::assertStringContainsString('</head><body>', $html);
        self::assertStringContainsString('</body></html>', $html);
    }

    public function testRenderFullDocument(): void
    {
        $doc = new HTMLDocument('My App', '<main>Hello</main>');
        $doc->addStylesheet('/css/app.css')
            ->addJavaScript('/js/app.js')
            ->addStyle('.container { max-width: 1200px; }')
            ->addMeta('description', 'My Application');

        $html = $doc->render();

        self::assertStringContainsString('<title>My App</title>', $html);
        self::assertStringContainsString('<link rel="stylesheet" type="text/css" href="/css/app.css">', $html);
        self::assertStringContainsString('<script type="module" src="/js/app.js"></script>', $html);
        self::assertStringContainsString('<style>.container { max-width: 1200px; }</style>', $html);
        self::assertStringContainsString('<meta name="description" content="My Application">', $html);
        self::assertStringContainsString('<body><main>Hello</main></body>', $html);
    }

    public function testStylesheetPathIsHtmlEscaped(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addStylesheet('/css/style.css" onload="alert(\'XSS\')" x="');
        $html = $doc->render();

        // Les guillemets doivent être échappés
        self::assertStringContainsString('&quot;', $html);
        // Les apostrophes doivent être échappées
        self::assertStringContainsString('&apos;', $html);
        // Le HTML ne doit pas contenir de guillemets non échappés dans les attributs
        self::assertStringNotContainsString('" onload="', $html);
        // Le script ne doit pas être exécutable (onload doit être échappé).
        self::assertStringNotContainsString(' onload="', $html);
    }

    public function testJavaScriptPathIsHtmlEscaped(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addJavaScript('/js/app.js" onload="alert(\'XSS\')" x="', 'module');
        $html = $doc->render();

        // Les guillemets doivent être échappés
        self::assertStringContainsString('&quot;', $html);
        // Les apostrophes doivent être échappées
        self::assertStringContainsString('&apos;', $html);
        // Le HTML ne doit pas contenir de guillemets non échappés dans les attributs
        self::assertStringNotContainsString('" onload="', $html);
        // Le script ne doit pas être exécutable (onload doit être échappé).
        self::assertStringNotContainsString(' onload="', $html);
    }

    public function testJavaScriptTypeIsHtmlEscaped(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addJavaScript('/js/app.js', 'module" onload="alert(\'XSS\')" x="');
        $html = $doc->render();

        // Les guillemets doivent être échappés
        self::assertStringContainsString('&quot;', $html);
        // Les apostrophes doivent être échappées
        self::assertStringContainsString('&apos;', $html);
        // Le HTML ne doit pas contenir de guillemets non échappés dans les attributs
        self::assertStringNotContainsString('" onload="', $html);
        // Le script ne doit pas être exécutable (onload doit être échappé).
        self::assertStringNotContainsString(' onload="', $html);
    }

    public function testMetaNameIsHtmlEscaped(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addMeta('description" onload="alert(\'XSS\')" x="', 'Safe content');
        $html = $doc->render();

        // Les guillemets doivent être échappés
        self::assertStringContainsString('&quot;', $html);
        // Les apostrophes doivent être échappées
        self::assertStringContainsString('&apos;', $html);
        // Le HTML ne doit pas contenir de guillemets non échappés dans les attributs
        self::assertStringNotContainsString('" onload="', $html);
        // Le script ne doit pas être exécutable (onload doit être échappé).
        self::assertStringNotContainsString(' onload="', $html);
    }

    public function testMetaContentIsHtmlEscaped(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addMeta('description', 'Test" onload="alert(\'XSS\')" x="');
        $html = $doc->render();

        // Les guillemets doivent être échappés
        self::assertStringContainsString('&quot;', $html);
        // Les apostrophes doivent être échappées
        self::assertStringContainsString('&apos;', $html);
        // Le HTML ne doit pas contenir de guillemets non échappés dans les attributs
        self::assertStringNotContainsString('" onload="', $html);
        // Le script ne doit pas être exécutable (onload doit être échappé).
        self::assertStringNotContainsString(' onload="', $html);
    }

    public function testLangAttributeIsHtmlEscaped(): void
    {
        $doc = new HTMLDocument('Title');
        // Utilisation de la réflexion pour modifier la propriété privée $lang
        $reflection = new \ReflectionClass($doc);
        $langProperty = $reflection->getProperty('lang');
        $langProperty->setAccessible(true);
        $langProperty->setValue($doc, 'en" onload="alert(\'XSS\')" x="');
        
        $html = $doc->render();

        // Les guillemets doivent être échappés
        self::assertStringContainsString('&quot;', $html);
        // Les apostrophes doivent être échappées
        self::assertStringContainsString('&apos;', $html);
        // Le HTML ne doit pas contenir de guillemets non échappés dans les attributs
        self::assertStringNotContainsString('" onload="', $html);
        // Le script ne doit pas être exécutable (onload doit être échappé).
        self::assertStringNotContainsString(' onload="', $html);
    }

    public function testSpecialCharactersInStylesheetPath(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addStylesheet('/css/style.css?param=value&other=<script>');
        $html = $doc->render();

        // Les caractères spéciaux doivent être échappés
        self::assertStringContainsString('&amp;', $html);
        self::assertStringContainsString('&lt;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testSpecialCharactersInJavaScriptPath(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addJavaScript('/js/app.js?param=value&other=<script>');
        $html = $doc->render();

        // Les caractères spéciaux doivent être échappés
        self::assertStringContainsString('&amp;', $html);
        self::assertStringContainsString('&lt;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testSpecialCharactersInMetaContent(): void
    {
        $doc = new HTMLDocument('Title');
        $doc->addMeta('description', 'Test & <script>alert("XSS")</script>');
        $html = $doc->render();

        // Les caractères spéciaux doivent être échappés
        self::assertStringContainsString('&amp;', $html);
        self::assertStringContainsString('&lt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('alert("XSS")', $html);
    }

    public function testTitleWithSpecialCharacters(): void
    {
        $doc = new HTMLDocument('Title & <script>alert("XSS")</script>');
        $html = $doc->render();

        // Les caractères spéciaux doivent être échappés
        self::assertStringContainsString('&amp;', $html);
        self::assertStringContainsString('&lt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('alert("XSS")', $html);
    }
}
