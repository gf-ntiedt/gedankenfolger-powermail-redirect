<h1>TYPO3 Extension Gedankenfolger Powermail Redirect<br/>(gedankenfolger-powermail-redirect)</h1>
<p>
    Powermail finisher for <a href="https://github.com/in2code-de/powermail" target="_blank">in2code/powermail</a> that redirects the user to a configurable target page after form submission, optionally passing selected field values as prefill GET parameters.
</p>
<p>
    First of all many thanks to the whole TYPO3 community, all supporters of TYPO3.
    Especially to <a href="https://typo3.org/" target="_blank">TYPO3-Team</a> and <a href="https://www.gedankenfolger.de/" target="_blank">Gedankenfolger GmbH</a>.
</p>

<h3>Contents of this file</h3>
<ol>
    <li><a href="#features">Features</a></li>
    <li><a href="#requirements">Requirements</a></li>
    <li><a href="#install">Install</a></li>
    <li><a href="#usage">Usage</a></li>
    <li><a href="#prefill">Prefill on Target Page</a></li>
    <li><a href="#debug">Debug Logging</a></li>
    <li><a href="#security">Security</a></li>
    <li><a href="#notes">Notes</a></li>
    <li><a href="#noticetrademark">Notice on Logo / Trademark Use</a></li>
</ol>
<hr/>

<h3 id="features">Features</h3>
<ol>
    <li>
        Redirect to any TYPO3 page after Powermail form submission — configured per content element, no TypoScript per form needed
    </li>
    <li>
        Field value transfer — select which form fields to pass as GET parameters in the <code>tx_powermail_pi1[field]</code> namespace so Powermail can prefill them on the target page
    </li>
    <li>
        FlexForm configuration tab — target page and field selection are added as a dedicated <strong>"Redirect Finisher"</strong> tab directly on the Powermail content element
    </li>
    <li>
        TYPO3 v13 Site Set — finisher registration via <code>Configuration/Sets/</code>; simply add the set as a site dependency, no manual TypoScript setup required
    </li>
    <li>
        Open-redirect protection — redirect URLs are validated against <code>TYPO3_SITE_URL</code> before the redirect fires
    </li>
    <li>
        Parameter injection protection — field marker names are validated against <code>[a-zA-Z0-9_]</code>; all values are <code>rawurlencode()</code>-escaped
    </li>
    <li>
        Controllable debug logging — step-by-step log output to <code>var/log/powermail_redirect_debug.log</code>, switchable via Site Set setting without touching code
    </li>
    <li>
        Compatible with Double Opt-In — redirect fires immediately after <code>createAction</code> so the user is forwarded to a pre-filled follow-up form while the opt-in e-mail runs in parallel
    </li>
</ol>

<h3 id="requirements">Requirements</h3>
<ul>
    <li><strong>TYPO3 CMS</strong>: <code>^13.0</code></li>
    <li><strong>PHP</strong>: <code>^8.2</code></li>
    <li><strong>in2code/powermail</strong>: <code>^13.0</code></li>
</ul>

<h3 id="install">Install</h3>
<ol>
    <li>
        Require the package via Composer:
        <br/><code>composer require gedankenfolger/gedankenfolger-powermail-redirect</code>
    </li>
    <li>
        Add the Site Set as a dependency in your site's <code>config/sites/[site]/config.yaml</code>:
        <pre><code>dependencies:
  - gedankenfolger/gedankenfolger-powermail-redirect</code></pre>
    </li>
    <li>
        Clear all TYPO3 caches:
        <br/><code>vendor/bin/typo3 cache:flush</code>
    </li>
</ol>

<h3 id="usage">Usage</h3>
<ol>
    <li>
        Open the <strong>Powermail content element</strong> in the TYPO3 backend.
    </li>
    <li>
        Navigate to the <strong>"Redirect Finisher"</strong> tab (added automatically to every Powermail CE after installation).
    </li>
    <li>
        Select the <strong>redirect target page</strong> via the page browser.
    </li>
    <li>
        Select the <strong>fields to transfer</strong> from the multiselect list. Only non-submit Powermail fields are listed.
        <br/><em>Note: The list shows fields from all Powermail forms, not just the current one. Select only fields that exist in this form.</em>
    </li>
    <li>
        Save – the redirect is active immediately.
    </li>
</ol>
<p>
    If the redirect target page is left empty, the finisher exits silently and Powermail continues its normal flow (e.g. showing a thank-you message).
</p>

<h3 id="prefill">Prefill on Target Page</h3>
<p>
    The transferred field values arrive as GET parameters in the <code>tx_powermail_pi1[field]</code> namespace.
    To prefill the form on the target page, add the following TypoScript to the target page (via site package or page TSconfig):
</p>
<pre><code>plugin.tx_powermail_pi1.settings.setup {
    prefill {
        e_mail = TEXT
        e_mail.data = GP:tx_powermail_pi1|field|e_mail

        # Add further markers as needed
        vorname = TEXT
        vorname.data = GP:tx_powermail_pi1|field|vorname
    }
}</code></pre>
<p>
    Replace <code>e_mail</code> / <code>vorname</code> with the actual field markers configured in your Powermail form.
    The marker is the value in the <strong>"Variable / Marker"</strong> field of each Powermail field record.
</p>

<h3 id="debug">Debug Logging</h3>
<p>
    Debug logging writes a step-by-step trace of every finisher invocation to <code>var/log/powermail_redirect_debug.log</code>.
    It is <strong>disabled by default</strong> and can be toggled without any code change.
</p>
<p>Enable via the TYPO3 backend:</p>
<ol>
    <li>Open <strong>Site Management › Sites › [your site] › Settings</strong></li>
    <li>Navigate to <strong>Gedankenfolger Powermail Redirect › Debug</strong></li>
    <li>Enable <strong>"Enable debug logging"</strong> and save</li>
</ol>
<p>Or directly in <code>config/sites/[site]/settings.yaml</code>:</p>
<pre><code>powermailRedirect:
  debugLog: true</code></pre>
<p>Remember to disable it again after debugging — the log file grows with every form submission.</p>

<h3 id="security">Security</h3>
<ul>
    <li><strong>Open-redirect protection</strong>: The generated target URL is checked against <code>TYPO3_SITE_URL</code> before the redirect is issued. External redirect targets are silently ignored.</li>
    <li><strong>Parameter injection protection</strong>: Field marker names are validated against <code>/^[a-zA-Z0-9_]+$/</code>. Markers not matching this pattern are skipped entirely.</li>
    <li><strong>Value encoding</strong>: All transferred values are encoded with <code>rawurlencode()</code> before being appended to the URL.</li>
    <li><strong>cHash</strong>: The target URL is generated via <code>typoLink_URL()</code> which automatically computes a valid <code>cHash</code>.</li>
</ul>

<h3 id="notes">Notes</h3>
<ul>
    <li>
        <strong>Confirmation page</strong> (<code>settings.flexform.main.confirmation</code>): Powermail's <code>confirmationAction</code> does not invoke finishers. The redirect fires after the user submits the confirmation — no special configuration needed.
    </li>
    <li>
        <strong>Double Opt-In</strong> (<code>settings.flexform.main.optin</code>): The redirect fires immediately after <code>createAction</code>, before the opt-in link is clicked. This is intentional: the user is forwarded to a pre-filled follow-up form while the opt-in e-mail runs in parallel.
    </li>
    <li>
        <strong>Field list scope</strong>: The "Fields to transfer" multiselect lists all Powermail fields across all forms, not only the fields of the currently configured form. Editors must manually select the correct fields for their form.
    </li>
    <li>
        <strong>Site Set dependency</strong>: The finisher TypoScript is only active for sites that include <code>gedankenfolger/gedankenfolger-powermail-redirect</code> in their <code>config.yaml</code> dependencies. Forms on other sites are unaffected.
    </li>
    <li>
        After installation or update, clear all TYPO3 caches: <code>vendor/bin/typo3 cache:flush</code>
    </li>
</ul>

<h3 id="noticetrademark">Notice on Logo / Trademark Use</h3>
<p>
The logo used in this extension is protected by copyright and, where applicable, trademark law and remains the exclusive property of Gedankenfolger GmbH.

Use of the logo is only permitted in the form provided here. Any changes, modifications, or adaptations of the logo, as well as its use in other projects, applications, or contexts, require the prior written consent of Gedankenfolger GmbH.

In forks, derivatives, or further developments of this extension, the logo may only be used if explicit consent has been granted by Gedankenfolger GmbH. Otherwise, the logo must be removed or replaced with an own, non-protected logo.

All other logos and icons bundled with this extension are either subject to the TYPO3 licensing terms (The MIT License (MIT), see https://typo3.org) or are in the public domain.
</p>
