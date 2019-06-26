# Register as a service

````
<service id="cache.adapter.wincache" class="StingerSoft\SymfonyWincache\Adapter\WincacheAdapter" abstract="true">
    <tag name="cache.pool" clearer="cache.default_clearer" />
    <tag name="monolog.logger" channel="cache" />
    <argument /> <!-- namespace -->
    <argument>0</argument> <!-- default lifetime -->
    <argument /> <!-- version -->
    <call method="setLogger">
        <argument type="service" id="logger" on-invalid="ignore" />
    </call>
</service>
````