<?php
declare(strict_types = 1);
namespace OpenGemeenten\CacheHeaders\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use function in_array;
use function md5;
use function preg_replace;

class CacheHeaders implements MiddlewareInterface
{
    /**
     * Adds cache headers for browser caching
     *
     * Always the header 'ETag: "<hash>"' will be send
     *
     * Browser does not have page in cache:
     * - Return status '200 OK'
     * - Send header 'cache-control: no-cache, must-revalidate'
     * - Send header
     *
     * When using gzip compression it might be the server is adding a pre- or postfix to the ETag
     *
     * Browser has page in cache
     * Browser:
     * - Sends header 'If-None-Match: "<hash>"' / 'If-None-Match: "<hash>-postfix"'
     *
     * Server:
     * - Checks if ETag is the same as header 'If-None-Match'
     * - Return status '304 Not Modified' if ETag is the same
     * - Return status '200 OK' when ETag has changed (content has changed)
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!($response instanceof NullResponse)
            && $GLOBALS['TSFE'] instanceof TypoScriptFrontendController
            && $GLOBALS['TSFE']->isStaticCacheble()
            && !$GLOBALS['TSFE']->isBackendUserLoggedIn()
            && !$GLOBALS['TSFE']->doWorkspacePreview()
            && (
                empty($GLOBALS['TSFE']->config['config']['sendCacheHeaders_onlyWhenLoginDeniedInBranch'])
                || empty($GLOBALS['TSFE']->checkIfLoginAllowedInBranch())
            )
            && isset($GLOBALS['TSFE']->config['config']['OpenGemeenten\CacheHeaders.']['sendCacheHeaders'])
            && (bool)$GLOBALS['TSFE']->config['config']['OpenGemeenten\CacheHeaders.']['sendCacheHeaders'] === true
        ) {
            $configuration = $GLOBALS['TSFE']->config['config'];

            // If cache headers are set within TYPO3, unset most of them
            if ((bool)$configuration['sendCacheHeaders'] === true) {
                $response = $response->withoutHeader('cache-control');
                $response = $response->withoutHeader('expires');
                $response = $response->withoutHeader('pragma');
            }

            $currentETag = '"' . md5($GLOBALS['TSFE']->content) . '"';

            // Browser sends the header 'if-none-match' with the Etag
            if ($request->hasHeader('if-none-match')) {
                $oldETags = $request->getHeader('if-none-match');

                $pattern = $configuration['OpenGemeenten\CacheHeaders.']['pattern'] ?? '^("[0-9a-fA-F]*)(\b-gzip\b|)(")$';
                $replacement = $configuration['OpenGemeenten\CacheHeaders.']['replacement'] ?? '$1$3';

                foreach ($oldETags as $key => $oldETag) {
                    $oldETags[$key] = preg_replace('/' . $pattern . '/', $replacement, $oldETag);
                }

                // If the current Etag is the same as one of the old, return 304, Not Modified
                if (in_array($currentETag, $oldETags)) {
                    $response = $response->withStatus(304);

                // Else return a 200 OK
                } else {
                    $response = $response->withStatus(200);
                }

            // Browser does not have anything in cache
            } else {
                $response = $response->withStatus(200);
            }

            // Applying “no-cache” does not mean that there is no cache at all.
            // it simply tells the browser to validate resources on the server before use it from the cache
            $response = $response->withHeader('cache-control', 'no-cache');
            $response = $response->withHeader('ETag', $currentETag);
        }

        return $response;
    }
}
