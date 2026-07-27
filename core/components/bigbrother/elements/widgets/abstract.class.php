<?php

require_once dirname(__DIR__, 2) . '/model/bigbrother/bigbrother.class.php';

use Google\Analytics\Admin\V1alpha\AnalyticsAdminServiceClient;
use Google\Analytics\Admin\V1alpha\Property;

abstract class BigBrotherAbstractDashboardWidget extends modDashboardWidgetInterface
{
    public static $initialized = false;

    /**
     * @var BigBrother
     */
    protected $bigbrother;
    /**
     * @var mixed
     */
    protected $assetsUrl;

    protected function initialize(): void
    {
        $this->bigbrother = new BigBrother($this->modx);

        if (static::$initialized) {
            return;
        }
        static::$initialized = true;

        $this->modx->lexicon->load('bigbrother:default');
        $this->assetsUrl = $this->bigbrother->config['assets_url'];
        $this->controller->addCss($this->assetsUrl . 'css/mgr.css?v=' . urlencode($this->bigbrother->version));

        if ($this->modx->getOption('bigbrother.scripts_dev')) {
            $this->controller->addJavascript($this->assetsUrl . 'node_modules/chart.js/dist/chart.umd.js?v=' . urlencode($this->bigbrother->version));
            $this->controller->addJavascript($this->assetsUrl . 'node_modules/luxon/build/global/luxon.min.js?v=' . urlencode($this->bigbrother->version));
            $this->controller->addJavascript($this->assetsUrl . 'node_modules/chartjs-adapter-luxon/dist/chartjs-adapter-luxon.umd.min.js?v=' . urlencode($this->bigbrother->version));
            $this->controller->addJavascript($this->assetsUrl . 'mgr/bigbrother.class.js?v=' . urlencode($this->bigbrother->version));
            $this->controller->addJavascript($this->assetsUrl . 'mgr/reports/visits.js?v=' . urlencode($this->bigbrother->version));
            $this->controller->addJavascript($this->assetsUrl . 'mgr/reports/key-metrics.js?v=' . urlencode($this->bigbrother->version));
            $this->controller->addJavascript($this->assetsUrl . 'mgr/reports/acquisition.js?v=' . urlencode($this->bigbrother->version));
            $this->controller->addJavascript($this->assetsUrl . 'mgr/reports/popular-pages.js?v=' . urlencode($this->bigbrother->version));
            $this->controller->addJavascript($this->assetsUrl . 'mgr/reports/top-countries.js?v=' . urlencode($this->bigbrother->version));
            $this->controller->addJavascript($this->assetsUrl . 'mgr/reports/top-referrers.js?v=' . urlencode($this->bigbrother->version));
        }
        else {
            $this->controller->addJavascript($this->assetsUrl . 'dist/dashboard.min.js?v=' . urlencode($this->bigbrother->version));
        }

        // Manually load the "bigbrother:default" lexicon so that translations can be accessed within the widgets.
        $this->controller->addHtml(<<<HTML
<script>
    Ext.applyIf(MODx.lang, {$this->modx->toJSON($this->modx->lexicon->loadCache('bigbrother'))});
</script>
HTML
        );

        $config = $this->modx->toJSON([
            'assetsUrl' => $this->assetsUrl,
            'connectorUrl' => $this->bigbrother->config['connector_url'],
            'version' => $this->bigbrother->version,
        ]);
        $this->controller->addHtml(<<<HTML
<script>
    BigBrother.config = $config;
</script>
HTML
        );
    }

    /**
     * Checks if the authorization and property selection is complete. If it is, returns true. If it isn't returns a warning to render.
     *
     * @return bool|string
     */
    protected function isAuthorized()
    {
        $property = $this->bigbrother->getPropertyID();
        try {
            $oauth = $this->bigbrother->getOAuth2();
        } catch (Exception $e) {
            return <<<HTML
<div class="bigbrother-inner-widget">
    <div class="bigbrother-block">
        <p class="bigbrother-warning">
            {$this->modx->lexicon('bigbrother.guzzle_error')}
        </p>
        
        <p class="bigbrother-credits bigbrother-credits--justified">
            <span class="bigbrother-credits__version">{$this->modx->lexicon('bigbrother.powered_by_bigbrother')} v{$this->bigbrother->version}</span>
            <a href="https://www.modmore.com/extras/bigbrother/?utm_source=bigbrother_footer" target="_blank" rel="noopener" class="bigbrother-credits__logo">
                <img src="{$this->modx->getOption('bigbrother.assets_url')}images/modmore.svg" alt="a modmore product">
            </a>
        </p>
    </div>
</div>
HTML;
        }
        if (empty($property) || empty($oauth->getAccessToken())) {
            $authLink = $this->bigbrother->getAuthorizeUrl();
            return <<<HTML
<div class="bigbrother-inner-widget">
    <div class="bigbrother-block">
        <p class="bigbrother-warning">
            {$this->modx->lexicon('bigbrother.not_authorized.warning')}
            <br><br>
            <a href="{$authLink}" class="x-btn">{$this->modx->lexicon('bigbrother.not_authorized.authorize_now')} &raquo;</a>
        </p>
        
        <p class="bigbrother-credits bigbrother-credits--justified">
            <span class="bigbrother-credits__version">{$this->modx->lexicon('bigbrother.powered_by_bigbrother')} v{$this->bigbrother->version}</span>
            <a href="https://www.modmore.com/extras/bigbrother/?utm_source=bigbrother_footer" target="_blank" rel="noopener" class="bigbrother-credits__logo">
                <img src="{$this->modx->getOption('bigbrother.assets_url')}images/modmore.svg" alt="a modmore product">
            </a>
        </p>
    </div>
</div>
HTML;
        }
        return true;
    }

    /**
     * @param int $propertyId
     * @return false|Property
     */
    protected function getGA4Property(int $propertyId)
    {
        $oAuth = $this->bigbrother->getOAuth2();

        try {
            $admin = new AnalyticsAdminServiceClient(['credentials' => $oAuth]);
            $property = $admin->getProperty('properties/' . $propertyId);
        } catch (\Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[BigBrother] Received ' . get_class($e) . ' verifying property: ' . $e->getMessage());
            return false;
        }

        return $property;
    }

    /**
     * Builds the widget title bar for a loaded property, or a full-widget error message
     * when the property cannot be loaded.
     *
     * @param int $propertyId
     * @param bool $failed Set to true when the property could not be loaded
     * @return string Title bar HTML on success; full widget error HTML when the property cannot be loaded
     */
    protected function getWidgetTitleBar(int $propertyId, bool &$failed = false): string
    {
        $property = $this->getGA4Property($propertyId);
        $hasAuthorizeAccess = $this->modx->context->checkPolicy('bigbrother_authorize');

        if ($property === false) {
            $failed = true;
            $authorizeAction = '';
            if ($hasAuthorizeAccess) {
                $authLink = $this->bigbrother->getAuthorizeUrl();
                $authorizeAction = <<<HTML
<br><br>
<a href="{$authLink}" class="x-btn">{$this->modx->lexicon('bigbrother.not_authorized.authorize_now')} &raquo;</a>
HTML;
            }

            return <<<HTML
<div class="bigbrother-inner-widget">
    <div class="bigbrother-block">
        <p class="bigbrother-warning">
            {$this->modx->lexicon('bigbrother.error.invalid_property')}
            {$authorizeAction}
        </p>

        <p class="bigbrother-credits bigbrother-credits--justified">
            <span class="bigbrother-credits__version">{$this->modx->lexicon('bigbrother.powered_by_bigbrother')} v{$this->bigbrother->version}</span>
            <a href="https://www.modmore.com/extras/bigbrother/?utm_source=bigbrother_footer" target="_blank" rel="noopener" class="bigbrother-credits__logo">
                <img src="{$this->modx->getOption('bigbrother.assets_url')}images/modmore.svg" alt="a modmore product">
            </a>
        </p>
    </div>
</div>
HTML;
        }

        $authorizeLink = '';
        if ($hasAuthorizeAccess) {
            $authorizeLink = <<<HTML
<a href="{$this->bigbrother->getAuthorizeUrl()}" title="{$this->modx->lexicon('bigbrother.authorization')}" class="authorize-link"><i class="icon icon-cog"></i></a>
HTML;
        }

        return <<<HTML
<div class="bigbrother-widget-title">
    <div>
        <span>{$this->modx->lexicon('bigbrother.widget_title', ['property_name' => $property->getDisplayName()])}</span>
        <span class="property-id">{$propertyId}</span>
        {$authorizeLink}
    </div>
    <div class="period-wrapper">
        <span id="bb-title-period" class="bigbrother-title-period">{$this->modx->lexicon('bigbrother.loading')}</span>
    </div>
</div>
HTML;
    }
}
