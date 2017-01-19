<input type="hidden" id="azp" name="azp" value="<?php echo $azpInfo->id; ?>" />
<div id="settings">
    <ul>
        <li>
            Your OAuth2 redirect_uri is <pre><?php echo "$tlaurl/cb"; ?></pre>
        </li>
        <li><input id="name" name="name" type="text" placeholder="Authority Name" value="'.<?php echo $azpInfo->name; ?>.'"></li>
        <li><input id="iss" name="iss" type="text" placeholder="Issuer Value (as provided by the Authority)" value="'.<?php echo $azpInfo->iss; ?>.'"></li>
        <li><input id="client_id" name="client_id" type="text" placeholder="client_id (as provided by the authority)" value="'.<?php echo $azpInfo->client_id; ?>.'"></li>
        <li><input id="url" name="url" type="text" placeholder="authory OAuth2 Url"></li>
        <li><input id="auth_type" name="auth_type" type="text" placeholder="moodle auth type" value="'.<?php echo $azpInfo->auth_type?>.'"></li>
        <li>
            <select id="flow" name="flow">
                <option value="code" <?php if ($azpInfo->flow === "code") echo 'selected="selected';?>>Code</option>
                <option value="implict" <?php if ($azpInfo->flow === "implicit") echo 'selected="selected';?>>>Implicit</option>
                <option value="hybrid" <?php if ($azpInfo->flow === "hybrid") echo 'selected="selected"';?>>Hybrid</option>
                <option value="assertion" <?php if ($azpInfo->flow === "assertion") echo 'selected="selected';?>>>Assertion</option>
            </select>
        </li>
    </ul>
</div>
<div>
    <input type="submit" id="storeazp" name="storeazp" value="Update Authority">
</div>
    <!-- list of keys -->
<div id="keyList">
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
    echo '<li><a href="'.$azpurl . $azp->id.'&kid=' . $key->id. '">';
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
        <ul>
            <li><input id="kid" name="kid" type="text" placeholder="Key Id (as provided by Authority)"></li>
            <li><input id="jku" name="jku" type="text" placeholder="jku (as provided by the authority)"></li>
            <li><textarea id="key" name="key"></textarea></li>
        </ul>
        <div>
            <input type="submit" id="storekey" name="storekey" value="Add Key">
        </div>
    </div>
</div>
