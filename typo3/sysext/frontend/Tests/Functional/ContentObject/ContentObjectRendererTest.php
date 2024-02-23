<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Frontend\Tests\Functional\ContentObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Tests\Functional\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Typolink\LinkFactory;
use TYPO3\CMS\Frontend\Typolink\LinkResultInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ContentObjectRendererTest extends FunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected array $pathsToProvideInTestInstance = ['typo3/sysext/frontend/Tests/Functional/Fixtures/Images' => 'fileadmin/user_upload'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        $this->writeSiteConfiguration(
            'test',
            $this->buildSiteConfiguration(1, '/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/en/'),
            ],
            $this->buildErrorHandlingConfiguration('Fluid', [404]),
        );
        GeneralUtility::flushInternalRuntimeCaches();
    }

    protected function getPreparedRequest(): ServerRequestInterface
    {
        $request = new ServerRequest('http://example.com/en/', 'GET', null, [], ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/en/']);
        return $request->withQueryParams(['id' => 1])->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
    }

    public static function getQueryDataProvider(): array
    {
        return [
            'testing empty conf' => [
                'tt_content',
                [],
                [
                    'SELECT' => '*',
                ],
            ],
            'testing #17284: adding uid/pid for workspaces' => [
                'tt_content',
                [
                    'selectFields' => 'header,bodytext',
                ],
                [
                    'SELECT' => 'header,bodytext, [tt_content].[uid] AS [uid], [tt_content].[pid] AS [pid], [tt_content].[t3ver_state] AS [t3ver_state]',
                ],
            ],
            'testing #17284: no need to add' => [
                'tt_content',
                [
                    'selectFields' => 'tt_content.*',
                ],
                [
                    'SELECT' => 'tt_content.*',
                ],
            ],
            'testing #17284: no need to add #2' => [
                'tt_content',
                [
                    'selectFields' => '*',
                ],
                [
                    'SELECT' => '*',
                ],
            ],
            'testing #29783: joined tables, prefix tablename' => [
                'tt_content',
                [
                    'selectFields' => 'tt_content.header,be_users.username',
                    'join' => 'be_users ON tt_content.cruser_id = be_users.uid',
                ],
                [
                    'SELECT' => 'tt_content.header,be_users.username, [tt_content].[uid] AS [uid], [tt_content].[pid] AS [pid], [tt_content].[t3ver_state] AS [t3ver_state]',
                ],
            ],
            'testing #34152: single count(*), add nothing' => [
                'tt_content',
                [
                    'selectFields' => 'count(*)',
                ],
                [
                    'SELECT' => 'count(*)',
                ],
            ],
            'testing #34152: single max(crdate), add nothing' => [
                'tt_content',
                [
                    'selectFields' => 'max(crdate)',
                ],
                [
                    'SELECT' => 'max(crdate)',
                ],
            ],
            'testing #34152: single min(crdate), add nothing' => [
                'tt_content',
                [
                    'selectFields' => 'min(crdate)',
                ],
                [
                    'SELECT' => 'min(crdate)',
                ],
            ],
            'testing #34152: single sum(is_siteroot), add nothing' => [
                'tt_content',
                [
                    'selectFields' => 'sum(is_siteroot)',
                ],
                [
                    'SELECT' => 'sum(is_siteroot)',
                ],
            ],
            'testing #34152: single avg(crdate), add nothing' => [
                'tt_content',
                [
                    'selectFields' => 'avg(crdate)',
                ],
                [
                    'SELECT' => 'avg(crdate)',
                ],
            ],
            'single distinct, add nothing' => [
                'tt_content',
                [
                    'selectFields' => 'DISTINCT crdate',
                ],
                [
                    'SELECT' => 'DISTINCT crdate',
                ],
            ],
            'testing #96321: pidInList=root does not raise PHP 8 warning' => [
                'tt_content',
                [
                    'selectFields' => '*',
                    'recursive' => '5',
                    'pidInList' => 'root',
                ],
                [
                    'SELECT' => '*',
                ],
            ],
        ];
    }

    /**
     * Check if sanitizeSelectPart works as expected
     */
    #[DataProvider('getQueryDataProvider')]
    #[Test]
    public function getQuery(string $table, array $conf, array $expected): void
    {
        $GLOBALS['TCA'] = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'disabled' => 'hidden',
                    ],
                ],
            ],
            'tt_content' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'disabled' => 'hidden',
                    ],
                    'versioningWS' => true,
                ],
            ],
        ];

        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByIdentifier('test');
        $typoScriptFrontendController = GeneralUtility::makeInstance(
            TypoScriptFrontendController::class,
            GeneralUtility::makeInstance(Context::class),
            $site,
            $site->getDefaultLanguage(),
            new PageArguments(1, '0', []),
            GeneralUtility::makeInstance(FrontendUserAuthentication::class)
        );
        $typoScriptFrontendController->sys_page = GeneralUtility::makeInstance(PageRepository::class);
        $subject = GeneralUtility::makeInstance(ContentObjectRenderer::class, $typoScriptFrontendController);

        $result = $subject->getQuery($table, $conf, true);

        $databasePlatform = (new ConnectionPool())->getConnectionForTable('tt_content')->getDatabasePlatform();
        foreach ($expected as $field => $value) {
            // Replace the MySQL backtick quote character with the actual quote character for the DBMS,
            if ($field === 'SELECT') {
                $quoteChar = $databasePlatform->getIdentifierQuoteCharacter();
                $value = str_replace(['[', ']'], [$quoteChar, $quoteChar], $value);
            }
            self::assertEquals($value, $result[$field]);
        }
    }

    #[Test]
    public function typolinkLinkResultIsInstanceOfLinkResultInterface(): void
    {
        $subject = new ContentObjectRenderer();
        $subject->setRequest($this->getPreparedRequest());
        $linkResult = $subject->typoLink('', ['parameter' => 'https://example.tld', 'returnLast' => 'result']);
        self::assertInstanceOf(LinkResultInterface::class, $linkResult);
    }

    #[Test]
    public function typoLinkReturnsOnlyLinkTextIfNoLinkResolvingIsPossible(): void
    {
        $linkService = $this->getMockBuilder(LinkService::class)->disableOriginalConstructor()->getMock();
        $linkService->method('resolve')->with('foo')->willThrowException(new InvalidPathException('', 1666303735));
        $linkFactory = new LinkFactory($linkService, $this->get(EventDispatcherInterface::class), $this->get(TypoLinkCodecService::class), $this->get('cache.runtime'), $this->get(SiteFinder::class));
        $linkFactory->setLogger(new NullLogger());
        GeneralUtility::addInstance(LinkFactory::class, $linkFactory);
        $subject = new ContentObjectRenderer();
        $subject->setRequest($this->getPreparedRequest());
        self::assertSame('foo', $subject->typoLink('foo', ['parameter' => 'foo']));
    }

    #[Test]
    public function typoLinkLogsErrorIfNoLinkResolvingIsPossible(): void
    {
        $linkService = $this->getMockBuilder(LinkService::class)->disableOriginalConstructor()->getMock();
        $linkService->method('resolve')->with('foo')->willThrowException(new InvalidPathException('', 1666303765));
        $linkFactory = new LinkFactory($linkService, $this->get(EventDispatcherInterface::class), $this->get(TypoLinkCodecService::class), $this->get('cache.runtime'), $this->get(SiteFinder::class));
        $logger = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $logger->expects(self::atLeastOnce())->method('warning')->with('The link could not be generated', self::anything());
        $linkFactory->setLogger($logger);
        GeneralUtility::addInstance(LinkFactory::class, $linkFactory);
        $subject = new ContentObjectRenderer();
        $subject->setRequest($this->getPreparedRequest());
        self::assertSame('foo', $subject->typoLink('foo', ['parameter' => 'foo']));
    }

    public static function typolinkReturnsCorrectLinksDataProvider(): array
    {
        return [
            'Link to url' => [
                'TYPO3',
                [
                    'directImageLink' => false,
                    'parameter' => 'http://typo3.org',
                ],
                '<a href="http://typo3.org">TYPO3</a>',
            ],
            'Link to url without schema' => [
                'TYPO3',
                [
                    'directImageLink' => false,
                    'parameter' => 'typo3.org',
                ],
                '<a href="http://typo3.org">TYPO3</a>',
            ],
            'Link to url without link text' => [
                '',
                [
                    'directImageLink' => false,
                    'parameter' => 'http://typo3.org',
                ],
                '<a href="http://typo3.org">http://typo3.org</a>',
            ],
            'Link to url with attributes' => [
                'TYPO3',
                [
                    'parameter' => 'http://typo3.org',
                    'ATagParams' => 'class="url-class"',
                    'extTarget' => '_blank',
                    'title' => 'Open new window',
                ],
                '<a href="http://typo3.org" target="_blank" class="url-class" rel="noreferrer" title="Open new window">TYPO3</a>',
            ],
            'Link to url with attributes and custom target name' => [
                'TYPO3',
                [
                    'parameter' => 'http://typo3.org',
                    'ATagParams' => 'class="url-class"',
                    'extTarget' => 'someTarget',
                    'title' => 'Open new window',
                ],
                '<a href="http://typo3.org" target="someTarget" class="url-class" rel="noreferrer" title="Open new window">TYPO3</a>',
            ],
            'Link to url with attributes in parameter' => [
                'TYPO3',
                [
                    'parameter' => 'http://typo3.org _blank url-class "Open new window"',
                ],
                '<a href="http://typo3.org" target="_blank" rel="noreferrer" title="Open new window" class="url-class">TYPO3</a>',
            ],
            'Link to url with attributes in parameter and custom target name' => [
                'TYPO3',
                [
                    'parameter' => 'http://typo3.org someTarget url-class "Open new window"',
                ],
                '<a href="http://typo3.org" target="someTarget" rel="noreferrer" title="Open new window" class="url-class">TYPO3</a>',
            ],
            'Link to url with script tag' => [
                '',
                [
                    'directImageLink' => false,
                    'parameter' => 'http://typo3.org<script>alert(123)</script>',
                ],
                '<a href="http://typo3.org&lt;script&gt;alert(123)&lt;/script&gt;">http://typo3.org&lt;script&gt;alert(123)&lt;/script&gt;</a>',
            ],
            'Link to email address' => [
                'Email address',
                [
                    'parameter' => 'foo@example.com',
                ],
                '<a href="mailto:foo@example.com">Email address</a>',
            ],
            'Link to email with mailto' => [
                'Send me an email',
                [
                    'parameter' => 'mailto:test@example.com',
                ],
                '<a href="mailto:test@example.com">Send me an email</a>',
            ],
            'Link to email address with subject + cc' => [
                'Email address',
                [
                    'parameter' => 'foo@bar.org?subject=This%20is%20a%20test',
                ],
                '<a href="mailto:foo@bar.org?subject=This%20is%20a%20test">Email address</a>',
            ],
            'Link to email address without link text' => [
                '',
                [
                    'parameter' => 'foo@bar.org',
                ],
                '<a href="mailto:foo@bar.org">foo@bar.org</a>',
            ],
            'Link to email with attributes' => [
                'Email address',
                [
                    'parameter' => 'foo@bar.org',
                    'ATagParams' => 'class="email-class"',
                    'title' => 'Write an email',
                ],
                '<a href="mailto:foo@bar.org" class="email-class" title="Write an email">Email address</a>',
            ],
            'Link to email with attributes in parameter' => [
                'Email address',
                [
                    'parameter' => 'foo@bar.org - email-class "Write an email"',
                ],
                '<a href="mailto:foo@bar.org" title="Write an email" class="email-class">Email address</a>',
            ],
            'Link url using stdWrap' => [
                'TYPO3',
                [
                    'parameter' => 'http://typo3.org',
                    'parameter.' => [
                        'cObject' => 'TEXT',
                        'cObject.' => [
                            'value' => 'http://typo3.com',
                        ],
                    ],
                ],
                '<a href="http://typo3.com">TYPO3</a>',
            ],
            'Link url using stdWrap with class attribute in parameter' => [
                'TYPO3',
                [
                    'parameter' => 'http://typo3.org - url-class',
                    'parameter.' => [
                        'cObject' => 'TEXT',
                        'cObject.' => [
                            'value' => 'http://typo3.com',
                        ],
                    ],
                ],
                '<a href="http://typo3.com" class="url-class">TYPO3</a>',
            ],
            'Link url using stdWrap with class attribute in parameter and overridden target' => [
                'TYPO3',
                [
                    'parameter' => 'http://typo3.org default-target url-class',
                    'parameter.' => [
                        'cObject' => 'TEXT',
                        'cObject.' => [
                            'value' => 'http://typo3.com new-target different-url-class',
                        ],
                    ],
                ],
                '<a href="http://typo3.com" target="new-target" rel="noreferrer" class="different-url-class">TYPO3</a>',
            ],
            'Link url using stdWrap with class attribute in parameter and overridden target and returnLast' => [
                'TYPO3',
                [
                    'parameter' => 'http://typo3.org default-target url-class',
                    'parameter.' => [
                        'cObject' => 'TEXT',
                        'cObject.' => [
                            'value' => 'http://typo3.com new-target different-url-class',
                        ],
                    ],
                    'returnLast' => 'url',
                ],
                'http://typo3.com',
            ],
            'Open in new window' => [
                'Nice Text',
                [
                    'parameter' => 'https://example.com 13x84:target=myexample',
                ],
                '<a href="https://example.com" target="myexample" data-window-url="https://example.com" data-window-target="myexample" data-window-features="width=13,height=84" rel="noreferrer">Nice Text</a>',
            ],
            'Open in new window with window name' => [
                'Nice Text',
                [
                    'parameter' => 'https://example.com 13x84',
                ],
                '<a href="https://example.com" target="FEopenLink" data-window-url="https://example.com" data-window-target="FEopenLink" data-window-features="width=13,height=84" rel="noreferrer">Nice Text</a>',
            ],
            'Link to file' => [
                'My file',
                [
                    'directImageLink' => false,
                    'parameter' => 'fileadmin/foo.bar',
                ],
                '<a href="fileadmin/foo.bar">My file</a>',
            ],
            'Link to file without link text' => [
                '',
                [
                    'directImageLink' => false,
                    'parameter' => 'fileadmin/foo.bar',
                ],
                '<a href="fileadmin/foo.bar">fileadmin/foo.bar</a>',
            ],
            'Link to file with attributes' => [
                'My file',
                [
                    'parameter' => 'fileadmin/foo.bar',
                    'ATagParams' => 'class="file-class"',
                    'fileTarget' => '_blank',
                    'title' => 'Title of the file',
                ],
                '<a href="fileadmin/foo.bar" target="_blank" class="file-class" title="Title of the file">My file</a>',
            ],
            'Link to file with attributes and additional href' => [
                'My file',
                [
                    'parameter' => 'fileadmin/foo.bar',
                    'ATagParams' => 'href="foo-bar"',
                    'fileTarget' => '_blank',
                    'title' => 'Title of the file',
                ],
                '<a href="fileadmin/foo.bar" target="_blank" title="Title of the file">My file</a>',
            ],
            'Link to file with attributes and additional href and class' => [
                'My file',
                [
                    'parameter' => 'fileadmin/foo.bar',
                    'ATagParams' => 'href="foo-bar" class="file-class"',
                    'fileTarget' => '_blank',
                    'title' => 'Title of the file',
                ],
                '<a href="fileadmin/foo.bar" target="_blank" class="file-class" title="Title of the file">My file</a>',
            ],
            'Link to file with attributes and additional class and href' => [
                'My file',
                [
                    'parameter' => 'fileadmin/foo.bar',
                    'ATagParams' => 'class="file-class" href="foo-bar"',
                    'fileTarget' => '_blank',
                    'title' => 'Title of the file',
                ],
                '<a href="fileadmin/foo.bar" target="_blank" class="file-class" title="Title of the file">My file</a>',
            ],
            'Link to file with attributes and additional class and href and title' => [
                'My file',
                [
                    'parameter' => 'fileadmin/foo.bar',
                    'ATagParams' => 'class="file-class" href="foo-bar" title="foo-bar"',
                    'fileTarget' => '_blank',
                    'title' => 'Title of the file',
                ],
                '<a href="fileadmin/foo.bar" target="_blank" class="file-class" title="Title of the file">My file</a>',
            ],
            'Link to file with attributes and empty ATagParams' => [
                'My file',
                [
                    'parameter' => 'fileadmin/foo.bar',
                    'ATagParams' => '',
                    'fileTarget' => '_blank',
                    'title' => 'Title of the file',
                ],
                '<a href="fileadmin/foo.bar" target="_blank" title="Title of the file">My file</a>',
            ],
            'Link to file with attributes in parameter' => [
                'My file',
                [
                    'parameter' => 'fileadmin/foo.bar _blank file-class "Title of the file"',
                ],
                '<a href="fileadmin/foo.bar" target="_blank" title="Title of the file" class="file-class">My file</a>',
            ],
            'Link to file with script tag in name' => [
                '',
                [
                    'directImageLink' => false,
                    'parameter' => 'fileadmin/<script>alert(123)</script>',
                ],
                '<a href="fileadmin/&lt;script&gt;alert(123)&lt;/script&gt;">fileadmin/&lt;script&gt;alert(123)&lt;/script&gt;</a>',
            ],
        ];
    }

    #[DataProvider('typolinkReturnsCorrectLinksDataProvider')]
    #[Test]
    public function typolinkReturnsCorrectLinksAndUrls(string $linkText, array $configuration, string $expectedResult): void
    {
        $subject = new ContentObjectRenderer();
        $subject->setRequest($this->getPreparedRequest());
        self::assertEquals($expectedResult, $subject->typoLink($linkText, $configuration));
    }

    public static function typolinkReturnsCorrectLinkForSpamEncryptedEmailsDataProvider(): array
    {
        return [
            'plain mail without mailto scheme' => [
                [
                    'spamProtectEmailAddresses' => 0,
                    'spamProtectEmailAddresses_atSubst' => '',
                    'spamProtectEmailAddresses_lastDotSubst' => '',
                ],
                'some.body@test.typo3.org',
                'some.body@test.typo3.org',
                '<a href="mailto:some.body@test.typo3.org">some.body@test.typo3.org</a>',
            ],
            'plain mail with mailto scheme' => [
                [
                    'spamProtectEmailAddresses' => 0,
                    'spamProtectEmailAddresses_atSubst' => '',
                    'spamProtectEmailAddresses_lastDotSubst' => '',
                ],
                'some.body@test.typo3.org',
                'mailto:some.body@test.typo3.org',
                '<a href="mailto:some.body@test.typo3.org">some.body@test.typo3.org</a>',
            ],
            'plain with at and dot substitution' => [
                [
                    'spamProtectEmailAddresses' => 0,
                    'spamProtectEmailAddresses_atSubst' => '(at)',
                    'spamProtectEmailAddresses_lastDotSubst' => '(dot)',
                ],
                'some.body@test.typo3.org',
                'mailto:some.body@test.typo3.org',
                '<a href="mailto:some.body@test.typo3.org">some.body@test.typo3.org</a>',
            ],
            'mono-alphabetic substitution offset +1' => [
                [
                    'spamProtectEmailAddresses' => 1,
                    'spamProtectEmailAddresses_atSubst' => '',
                    'spamProtectEmailAddresses_lastDotSubst' => '',
                ],
                'some.body@test.typo3.org',
                'mailto:some.body@test.typo3.org',
                '<a href="#" data-mailto-token="nbjmup+tpnf/cpezAuftu/uzqp4/psh" data-mailto-vector="1">some.body(at)test.typo3.org</a>',
            ],
            'mono-alphabetic substitution offset +1 with at substitution' => [
                [
                    'spamProtectEmailAddresses' => 1,
                    'spamProtectEmailAddresses_atSubst' => '@',
                    'spamProtectEmailAddresses_lastDotSubst' => '',
                ],
                'some.body@test.typo3.org',
                'mailto:some.body@test.typo3.org',
                '<a href="#" data-mailto-token="nbjmup+tpnf/cpezAuftu/uzqp4/psh" data-mailto-vector="1">some.body@test.typo3.org</a>',
            ],
            'mono-alphabetic substitution offset +1 with at and dot substitution' => [
                [
                    'spamProtectEmailAddresses' => 1,
                    'spamProtectEmailAddresses_atSubst' => '(at)',
                    'spamProtectEmailAddresses_lastDotSubst' => '(dot)',
                ],
                'some.body@test.typo3.org',
                'mailto:some.body@test.typo3.org',
                '<a href="#" data-mailto-token="nbjmup+tpnf/cpezAuftu/uzqp4/psh" data-mailto-vector="1">some.body(at)test.typo3(dot)org</a>',
            ],
            'mono-alphabetic substitution offset -1 with at and dot substitution' => [
                [
                    'spamProtectEmailAddresses' => -1,
                    'spamProtectEmailAddresses_atSubst' => '(at)',
                    'spamProtectEmailAddresses_lastDotSubst' => '(dot)',
                ],
                'some.body@test.typo3.org',
                'mailto:some.body@test.typo3.org',
                '<a href="#" data-mailto-token="lzhksn9rnld-ancxZsdrs-sxon2-nqf" data-mailto-vector="-1">some.body(at)test.typo3(dot)org</a>',
            ],
            'mono-alphabetic substitution offset -1 with at and dot markup substitution' => [
                [
                    'spamProtectEmailAddresses' => -1,
                    'spamProtectEmailAddresses_atSubst' => '<span class="at"></span>',
                    'spamProtectEmailAddresses_lastDotSubst' => '<span class="dot"></span>',
                ],
                'some.body@test.typo3.org',
                'mailto:some.body@test.typo3.org',
                '<a href="#" data-mailto-token="lzhksn9rnld-ancxZsdrs-sxon2-nqf" data-mailto-vector="-1">some.body<span class="at"></span>test.typo3<span class="dot"></span>org</a>',
            ],
            'mono-alphabetic substitution offset 2 with at and dot substitution and encoded subject' => [
                [
                    'spamProtectEmailAddresses' => 2,
                    'spamProtectEmailAddresses_atSubst' => '(at)',
                    'spamProtectEmailAddresses_lastDotSubst' => '(dot)',
                ],
                'some.body@test.typo3.org',
                'mailto:some.body@test.typo3.org?subject=foo%20bar',
                '<a href="#" data-mailto-token="ocknvq,uqog0dqfaBvguv0varq50qti?uwdlgev=hqq%42dct" data-mailto-vector="2">some.body(at)test.typo3(dot)org</a>',
            ],
        ];
    }

    #[DataProvider('typolinkReturnsCorrectLinkForSpamEncryptedEmailsDataProvider')]
    #[Test]
    public function typolinkReturnsCorrectLinkForSpamEncryptedEmails(array $tsfeConfig, string $linkText, string $parameter, string $expected): void
    {
        $tsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $tsfe->config['config'] = $tsfeConfig;
        $subject = new ContentObjectRenderer($tsfe);
        self::assertEquals($expected, $subject->typoLink($linkText, ['parameter' => $parameter]));
    }

    public static function typolinkReturnsCorrectLinksForFilesWithAbsRefPrefixDataProvider(): array
    {
        return [
            'Link to file' => [
                'My file',
                [
                    'directImageLink' => false,
                    'parameter' => 'fileadmin/foo.bar',
                ],
                '/',
                '<a href="/fileadmin/foo.bar">My file</a>',
            ],
            'Link to file with longer absRefPrefix' => [
                'My file',
                [
                    'directImageLink' => false,
                    'parameter' => 'fileadmin/foo.bar',
                ],
                '/sub/',
                '<a href="/sub/fileadmin/foo.bar">My file</a>',
            ],
            'Link to absolute file' => [
                'My file',
                [
                    'directImageLink' => false,
                    'parameter' => '/images/foo.bar',
                ],
                '/',
                '<a href="/images/foo.bar">My file</a>',
            ],
            'Link to absolute file with longer absRefPrefix' => [
                'My file',
                [
                    'directImageLink' => false,
                    'parameter' => '/images/foo.bar',
                ],
                '/sub/',
                '<a href="/images/foo.bar">My file</a>',
            ],
            'Link to absolute file with identical longer absRefPrefix' => [
                'My file',
                [
                    'directImageLink' => false,
                    'parameter' => '/sub/fileadmin/foo.bar',
                ],
                '/sub/',
                '<a href="/sub/fileadmin/foo.bar">My file</a>',
            ],
            'Link to file with empty absRefPrefix' => [
                'My file',
                [
                    'directImageLink' => false,
                    'parameter' => 'fileadmin/foo.bar',
                ],
                '',
                '<a href="fileadmin/foo.bar">My file</a>',
            ],
            'Link to absolute file with empty absRefPrefix' => [
                'My file',
                [
                    'directImageLink' => false,
                    'parameter' => '/fileadmin/foo.bar',
                ],
                '',
                '<a href="/fileadmin/foo.bar">My file</a>',
            ],
            'Link to file with attributes with absRefPrefix' => [
                'My file',
                [
                    'parameter' => 'fileadmin/foo.bar',
                    'ATagParams' => 'class="file-class"',
                    'title' => 'Title of the file',
                ],
                '/',
                '<a href="/fileadmin/foo.bar" class="file-class" title="Title of the file">My file</a>',
            ],
            'Link to file with attributes with longer absRefPrefix' => [
                'My file',
                [
                    'parameter' => 'fileadmin/foo.bar',
                    'ATagParams' => 'class="file-class"',
                    'title' => 'Title of the file',
                ],
                '/sub/',
                '<a href="/sub/fileadmin/foo.bar" class="file-class" title="Title of the file">My file</a>',
            ],
            'Link to absolute file with attributes with absRefPrefix' => [
                'My file',
                [
                    'parameter' => '/images/foo.bar',
                    'ATagParams' => 'class="file-class"',
                    'title' => 'Title of the file',
                ],
                '/',
                '<a href="/images/foo.bar" class="file-class" title="Title of the file">My file</a>',
            ],
            'Link to absolute file with attributes with longer absRefPrefix' => [
                'My file',
                [
                    'parameter' => '/images/foo.bar',
                    'ATagParams' => 'class="file-class"',
                    'title' => 'Title of the file',
                ],
                '/sub/',
                '<a href="/images/foo.bar" class="file-class" title="Title of the file">My file</a>',
            ],
            'Link to absolute file with attributes with identical longer absRefPrefix' => [
                'My file',
                [
                    'parameter' => '/sub/fileadmin/foo.bar',
                    'ATagParams' => 'class="file-class"',
                    'title' => 'Title of the file',
                ],
                '/sub/',
                '<a href="/sub/fileadmin/foo.bar" class="file-class" title="Title of the file">My file</a>',
            ],
        ];
    }

    #[DataProvider('typolinkReturnsCorrectLinksForFilesWithAbsRefPrefixDataProvider')]
    #[Test]
    public function typolinkReturnsCorrectLinksForFilesWithAbsRefPrefix(string $linkText, array $configuration, string $absRefPrefix, string $expectedResult): void
    {
        $tsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $tsfe->absRefPrefix = $absRefPrefix;
        $subject = new ContentObjectRenderer($tsfe);
        self::assertEquals($expectedResult, $subject->typoLink($linkText, $configuration));
    }

    public static function typoLinkProperlyEncodesLinkResultDataProvider(): array
    {
        return [
            'Link to file' => [
                'My file',
                [
                    'directImageLink' => false,
                    'parameter' => '/fileadmin/foo.bar',
                    'returnLast' => 'result',
                ],
                json_encode([
                    'href' => '/fileadmin/foo.bar',
                    'target' => null,
                    'class' => null,
                    'title' => null,
                    'linkText' => 'My file',
                    'additionalAttributes' => [],
                ]),
            ],
            'Link example' => [
                'My example',
                [
                    'directImageLink' => false,
                    'parameter' => 'https://example.tld',
                    'returnLast' => 'result',
                ],
                json_encode([
                    'href' => 'https://example.tld',
                    'target' => null,
                    'class' => null,
                    'title' => null,
                    'linkText' => 'My example',
                    'additionalAttributes' => [],
                ]),
            ],
            'Link to file with attributes' => [
                'My file',
                [
                    'parameter' => '/fileadmin/foo.bar',
                    'ATagParams' => 'class="file-class"',
                    'returnLast' => 'result',
                ],
                json_encode([
                    'href' => '/fileadmin/foo.bar',
                    'target' => null,
                    'class' => 'file-class',
                    'title' => null,
                    'linkText' => 'My file',
                    'additionalAttributes' => [],
                ]),
            ],
            'Link parsing' => [
                'Url',
                [
                    'parameter' => 'https://example.com _blank css-class "test title"',
                    'returnLast' => 'result',
                ],
                json_encode([
                    'href' => 'https://example.com',
                    'target' => '_blank',
                    'class' => 'css-class',
                    'title' => 'test title',
                    'linkText' => 'Url',
                    'additionalAttributes' => ['rel' => 'noreferrer'],
                ]),
            ],
        ];
    }

    #[DataProvider('typoLinkProperlyEncodesLinkResultDataProvider')]
    #[Test]
    public function typoLinkProperlyEncodesLinkResult(string $linkText, array $configuration, string $expectedResult): void
    {
        $subject = new ContentObjectRenderer();
        $subject->setRequest($this->getPreparedRequest());
        self::assertEquals($expectedResult, $subject->typoLink($linkText, $configuration));
    }

    #[Test]
    public function searchWhereWithTooShortSearchWordWillReturnValidWhereStatement(): void
    {
        $tsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $subject = new ContentObjectRenderer($tsfe);
        $subject->setRequest($this->getPreparedRequest());
        $subject->start([], 'tt_content');

        $expected = '';
        $actual = $subject->searchWhere('ab', 'header,bodytext', 'tt_content');
        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function libParseFuncProperlyKeepsTagsUnescaped(): void
    {
        $tsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $subject = new ContentObjectRenderer($tsfe);
        $subject->setRequest($this->getPreparedRequest());
        $subject->setLogger(new NullLogger());
        $input = 'This is a simple inline text, no wrapping configured';
        $result = $subject->parseFunc($input, $this->getLibParseFunc());
        self::assertEquals($input, $result);

        $input = '<p>A one liner paragraph</p>';
        $result = $subject->parseFunc($input, $this->getLibParseFunc());
        self::assertEquals($input, $result);

        $input = 'A one liner paragraph
And another one';
        $result = $subject->parseFunc($input, $this->getLibParseFunc());
        self::assertEquals($input, $result);

        $input = '<p>A one liner paragraph</p><p>And another one and the spacing is kept</p>';
        $result = $subject->parseFunc($input, $this->getLibParseFunc());
        self::assertEquals($input, $result);

        $input = '<p>text to a <a href="https://www.example.com">an external page</a>.</p>';
        $result = $subject->parseFunc($input, $this->getLibParseFunc());
        self::assertEquals($input, $result);
    }

    protected function getLibParseFunc(): array
    {
        return [
            'htmlSanitize' => '1',
            'makelinks' => '1',
            'makelinks.' => [
                'http.' => [
                    'keep' => '{$styles.content.links.keep}',
                    'extTarget' => '',
                    'mailto.' => [
                        'keep' => 'path',
                    ],
                ],
            ],
            'tags.' => [
                'link' => 'TEXT',
                'link.' => [
                    'current' => '1',
                    'typolink.' => [
                        'parameter.' => [
                            'data' => 'parameters : allParams',
                        ],
                    ],
                    'parseFunc.' => [
                        'constants' => '1',
                    ],
                ],
                'a' => 'TEXT',
                'a.' => [
                    'current' => '1',
                    'typolink.' => [
                        'parameter.' => [
                            'data' => 'parameters:href',
                        ],
                    ],
                ],
            ],

            'allowTags' => 'a, abbr, acronym, address, article, aside, b, bdo, big, blockquote, br, caption, center, cite, code, col, colgroup, dd, del, dfn, dl, div, dt, em, font, footer, header, h1, h2, h3, h4, h5, h6, hr, i, img, ins, kbd, label, li, link, meta, nav, ol, p, pre, q, samp, sdfield, section, small, span, strike, strong, style, sub, sup, table, thead, tbody, tfoot, td, th, tr, title, tt, u, ul, var',
            'denyTags' => '*',
            'sword' => '<span class="csc-sword">|</span>',
            'constants' => '1',
            'nonTypoTagStdWrap.' => [
                'HTMLparser' => '1',
                'HTMLparser.' => [
                    'keepNonMatchedTags' => '1',
                    'htmlSpecialChars' => '2',
                ],
            ],
        ];
    }

    public static function checkIfReturnsExpectedValuesDataProvider(): iterable
    {
        yield 'isNull returns true if stdWrap returns null' => [
            'configuration' => [
                'isNull.' => [
                    'field' => 'unknown',
                ],
            ],
            'expected' => true,
        ];

        yield 'isNull returns false if stdWrap returns not null' => [
            'configuration' => [
                'isNull.' => [
                    'field' => 'known',
                ],
            ],
            'expected' => false,
        ];
    }

    #[DataProvider('checkIfReturnsExpectedValuesDataProvider')]
    #[Test]
    public function checkIfReturnsExpectedValues(array $configuration, bool $expected): void
    {
        $subject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $subject->data = [
            'known' => 'somevalue',
        ];
        self::assertSame($expected, $subject->checkIf($configuration));
    }

    public static function imageLinkWrapWrapsTheContentAsConfiguredDataProvider(): iterable
    {
        $width = 900;
        $height = 600;
        $processingWidth = $width . 'm';
        $processingHeight = $height . 'm';
        $defaultConfiguration = [
            'wrap' => '<a href="javascript:close();"> | </a>',
            'width' => $processingWidth,
            'height' => $processingHeight,
            'JSwindow' => '1',
            'JSwindow.' => [
                'newWindow' => '0',
            ],
            'crop.' => [
                'data' => 'file:current:crop',
            ],
            'linkParams.' => [
                'ATagParams.' => [
                    'dataWrap' => 'class="lightbox" rel="lightbox[{field:uid}]"',
                ],
            ],
            'enable' => true,
        ];
        $imageTag = '<img class="image-embed-item" src="/fileadmin/_processed_/team-t3board10-processed.jpg" width="500" height="300" loading="lazy" alt="" />';
        $windowFeatures = 'width=' . $width . ',height=' . $height . ',status=0,menubar=0';

        $configurationEnableFalse = $defaultConfiguration;
        $configurationEnableFalse['enable'] = false;
        yield 'enable => false configuration returns image tag as is.' => [
            'content' => $imageTag,
            'configuration' => $configurationEnableFalse,
            'expected' => [$imageTag => true],
        ];

        yield 'image is wrapped with link tag.' => [
            'content' => $imageTag,
            'configuration' => $defaultConfiguration,
            'expected' => [
                '<a href="index.php?eID=tx_cms_showpic&amp;file=1' => true,
                $imageTag . '</a>' => true,
                'data-window-features="' . $windowFeatures => true,
                'data-window-target="thePicture"' => true,
                ' target="thePicture' => true,
            ],
        ];

        $paramsConfiguration = $defaultConfiguration;
        $windowFeaturesOverrides = 'width=420,status=1,menubar=1,foo=bar';
        $windowFeaturesOverriddenExpected = 'width=420,height=' . $height . ',status=1,menubar=1,foo=bar';
        $paramsConfiguration['JSwindow.']['params'] = $windowFeaturesOverrides;
        yield 'JSWindow.params overrides windowParams' => [
            'content' => $imageTag,
            'configuration' => $paramsConfiguration,
            'expected' => [
                'data-window-features="' . $windowFeaturesOverriddenExpected => true,
            ],
        ];

        $newWindowConfiguration = $defaultConfiguration;
        $newWindowConfiguration['JSwindow.']['newWindow'] = '1';
        yield 'data-window-target is not "thePicture" if newWindow = 1 but an md5 hash of the url.' => [
            'content' => $imageTag,
            'configuration' => $newWindowConfiguration,
            'expected' => [
                'data-window-target="thePicture' => false,
            ],
        ];

        $newWindowConfiguration = $defaultConfiguration;
        $newWindowConfiguration['JSwindow.']['expand'] = '20,40';
        $windowFeaturesExpand = 'width=' . ($width + 20) . ',height=' . ($height + 40) . ',status=0,menubar=0';
        yield 'expand increases the window size by its value' => [
            'content' => $imageTag,
            'configuration' => $newWindowConfiguration,
            'expected' => [
                'data-window-features="' . $windowFeaturesExpand => true,
            ],
        ];

        $directImageLinkConfiguration = $defaultConfiguration;
        $directImageLinkConfiguration['directImageLink'] = '1';
        yield 'Direct image link does not use eID and links directly to the image.' => [
            'content' => $imageTag,
            'configuration' => $directImageLinkConfiguration,
            'expected' => [
                'index.php?eID=tx_cms_showpic&amp;file=1' => false,
                '<a href="fileadmin/_processed_' => true,
                'data-window-url="fileadmin/_processed_' => true,
            ],
        ];

        // @todo Error: Object of class TYPO3\CMS\Core\Resource\FileReference could not be converted to string
        //        $altUrlConfiguration = $defaultConfiguration;
        //        $altUrlConfiguration['JSwindow.']['altUrl'] = '/alternative-url';
        //        yield 'JSwindow.altUrl forces an alternative url.' => [
        //            'content' => $imageTag,
        //            'configuration' => $altUrlConfiguration,
        //            'expected' => [
        //                '<a href="/alternative-url' => true,
        //                'data-window-url="/alternative-url' => true,
        //            ],
        //        ];

        $altUrlConfigurationNoDefault = $defaultConfiguration;
        $altUrlConfigurationNoDefault['JSwindow.']['altUrl'] = '/alternative-url';
        $altUrlConfigurationNoDefault['JSwindow.']['altUrl_noDefaultParams'] = '1';
        yield 'JSwindow.altUrl_noDefaultParams removes the default ?file= params' => [
            'content' => $imageTag,
            'configuration' => $altUrlConfigurationNoDefault,
            'expected' => [
                '<a href="/alternative-url' => true,
                'data-window-url="/alternative-url' => true,
                'data-window-url="/alternative-url?file=' => false,
            ],
        ];

        $targetConfiguration = $defaultConfiguration;
        $targetConfiguration['target'] = 'myTarget';
        yield 'Setting target overrides the default target "thePicture.' => [
            'content' => $imageTag,
            'configuration' => $targetConfiguration,
            'expected' => [
                ' target="myTarget"' => true,
                'data-window-target="thePicture"' => true,
            ],
        ];

        $parameters = [
            'sample' => '1',
            'width' => $processingWidth,
            'height' => $processingHeight,
            'effects' => 'gamma=1.3 | flip | rotate=180',
            'bodyTag' => '<body style="margin:0; background:#fff;">',
            'title' => 'My Title',
            'wrap' => '<div class="my-wrap">|</div>',
            'crop' => '{"default":{"cropArea":{"x":0,"y":0,"width":1,"height":1},"selectedRatio":"NaN","focusArea":null}}',
        ];
        $parameterConfiguration = array_replace($defaultConfiguration, $parameters);
        $expectedParameters = $parameters;
        $expectedParameters['sample'] = 1;
        yield 'Setting one of [width, height, effects, bodyTag, title, wrap, crop, sample] will add them to the parameter list.' => [
            'content' => $imageTag,
            'configuration' => $parameterConfiguration,
            'expected' => [],
            'expectedParams' => $expectedParameters,
        ];

        $stdWrapConfiguration = $defaultConfiguration;
        $stdWrapConfiguration['stdWrap.'] = [
            'append' => 'TEXT',
            'append.' => [
                'value' => 'appendedString',
            ],
        ];
        yield 'stdWrap is called upon the whole content.' => [
            'content' => $imageTag,
            'configuration' => $stdWrapConfiguration,
            'expected' => [
                'appendedString' => true,
            ],
        ];
    }

    #[DataProvider('imageLinkWrapWrapsTheContentAsConfiguredDataProvider')]
    #[Test]
    public function imageLinkWrapWrapsTheContentAsConfigured(string $content, array $configuration, array $expected, array $expectedParams = []): void
    {
        $this->importCSVDataSet(__DIR__ . '/DataSet/FileReferences.csv');
        $fileReferenceData = [
            'uid' => 1,
            'uid_local' => 1,
            'crop' => '{"default":{"cropArea":{"x":0,"y":0,"width":1,"height":1},"selectedRatio":"NaN","focusArea":null}}',
        ];
        $fileReference = new FileReference($fileReferenceData);
        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByIdentifier('test');
        $typoScriptFrontendController = GeneralUtility::makeInstance(
            TypoScriptFrontendController::class,
            GeneralUtility::makeInstance(Context::class),
            $site,
            $site->getDefaultLanguage(),
            new PageArguments(1, '0', []),
            GeneralUtility::makeInstance(FrontendUserAuthentication::class)
        );
        $subject = GeneralUtility::makeInstance(ContentObjectRenderer::class, $typoScriptFrontendController);
        $subject->setCurrentFile($fileReference);
        $subject->setRequest($this->getPreparedRequest());
        $result = $subject->imageLinkWrap($content, $fileReference, $configuration);

        foreach ($expected as $expectedString => $shouldContain) {
            if ($shouldContain) {
                self::assertStringContainsString($expectedString, $result);
            } else {
                self::assertStringNotContainsString($expectedString, $result);
            }
        }

        if ($expectedParams !== []) {
            preg_match('@href="(.*)"@U', $result, $matches);
            self::assertArrayHasKey(1, $matches);
            $url = parse_url(html_entity_decode($matches[1]));
            parse_str($url['query'], $queryResult);
            $base64_string = implode('', $queryResult['parameters']);
            $base64_decoded = base64_decode($base64_string);
            $jsonDecodedArray = json_decode($base64_decoded, true);
            self::assertSame($expectedParams, $jsonDecodedArray);
        }
    }

    #[Test]
    public function getImgResourceRespectsFileReferenceObjectCropData(): void
    {
        $this->importCSVDataSet(__DIR__ . '/DataSet/FileReferences.csv');
        $fileReferenceData = [
            'uid' => 1,
            'uid_local' => 1,
            'crop' => '{"default":{"cropArea":{"x":0,"y":0,"width":0.5,"height":0.5},"selectedRatio":"NaN","focusArea":null}}',
        ];
        $fileReference = new FileReference($fileReferenceData);

        $subject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $result = $subject->getImgResource($fileReference, []);

        $expectedWidth = 512;
        $expectedHeight = 341;

        self::assertEquals($expectedWidth, $result[0]);
        self::assertEquals($expectedHeight, $result[1]);
    }
}
