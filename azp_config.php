<input type="hidden" id="azp" name="azp" value="<?php echo $azpInfo->id; ?>" />
<div id="settings">
    <p>
            Your OAuth2 redirect_uri is <pre><?php echo "$tlaurl/cb"; ?></pre>
    </p>
        <p><label for="name">Authority's Display Name</label><input id="name" name="name" type="text" placeholder="Authority Name" value="<?php echo $azpInfo->name; ?>"></p>
        <p><label for="iss">Issuer Id (as provided by the Authority)</label><input id="issuer" name="issuer" type="text" placeholder="Issuer Value " value="<?php echo $azpInfo->issuer; ?>"></p>
        <p><label for="client_id">client_id (as provided by the authority)</label><input id="client_id" name="client_id" type="text" placeholder="client_id (as provided by the authority)" value="<?php echo $azpInfo->client_id; ?>"></p>
        <p><label for="url">authority OAuth2 Base Url</label><input id="url" name="url" type="text" placeholder="authority OAuth2 Url" value="<?php echo $azpInfo->url; ?>"></p>
        <p><label for="auth_type">moodle auth type (optional)</label><input id="auth_type" name="auth_type" type="text" placeholder="moodle auth type" value="<?php echo $azpInfo->auth_type?>"></p>
        <p> <label for="flow">OAuth2/OpenID Connect Flow Type</label>
            <select id="flow" name="flow">
                <option value="code" <?php if ($azpInfo->flow === "code") echo 'selected="selected';?>">Code</option>
                <option value="implict" <?php if ($azpInfo->flow === "implicit") echo 'selected="selected';?>>Implicit</option>
                <option value="hybrid" <?php if ($azpInfo->flow === "hybrid") echo 'selected="selected"';?>>Hybrid</option>
                <option value="assertion" <?php if ($azpInfo->flow === "assertion") echo 'selected="selected"';?>>Assertion</option>
            </select>
        </p>
</div>
<div>
    <input type="submit" id="storeazp" name="storeazp" value="Update Authority">
</div>
    <!-- list of keys -->
<div id="keyList">
    <h2>Registered Keys</h2>
    <ul>
        <?php
foreach ($keyList as $key) {
    if (empty($key->kid) && empty($key->jku)) {
        continue;
    }

    if (!empty($key->token_id)) {
        // skip secondary keys
        continue;
    }
    echo '<li><a href="'.$azpurl . $azpInfo->id.'&keyid=' . $key->id. '">';
    if (!empty($key->kid)) {
        echo $key->kid;
    }
    elseif (!empty($key->jku)) {
        echo $key->jku;
    }
    echo '</a></li>';
}
            ?>
    </ul>
    <div>
        <input type="hidden" id="keyid" name="keyid" value=""/>

            <p><label for="kid">Key Id (as provided by Authority)</label><input id="kid" name="kid" type="text" placeholder="Key Id (as provided by Authority)"></p>
            <p><label for="jku">JWK Source URL (as provided by the Authority)</label><input id="jku" name="jku" type="text" placeholder="jku (as provided by the authority)"></p>
            <p><label for="crypt_key">Key (as provided by Authority)</label><textarea id="crypt_key" name="crypt_key"></textarea></p>
        <div>
            <input type="submit" id="storekey" name="storekey" value="Add Key">
        </div>
    </div>
</div>
