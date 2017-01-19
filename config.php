
<div id="settings">
    <!-- global private key settings -->
    <div id="private_key">
<?php
if (isset($PK)) {
    echo "<div>Private Key is present<div>";
}
?>
    <div>
        <textarea id="pk" name="pk"></textarea>
    </div>
    <div>
        <input type="submit" id="storepk" name="storepk" value="Update">
    </div>
    </div>
    <!-- list of authorities -->
    <div id="authorityList">
        <ul>
            <?php
foreach ($authorities as $azp) {
    echo '<li><a href="'.$azpurl . $azp->id.'">'.$azp->name.'</a></li>';
}
            ?>
        </ul>
        <div>
            <ul>
                <li>
                    Your OAuth2 redirect_uri is <pre><?php echo "$tlaurl/cb"?></pre>
                </li>
                <li><input id="name" name="name" type="text" placeholder="Authority Name"></li>
                <li><input id="iss" name="iss" type="text" placeholder="Issuer Value (as provided by the authority)"></li>
                <li><input id="client_id" name="client_id" type="text" placeholder="client_id (as provided by the authority)"></li>
                <li><input id="url" name="url" type="text" placeholder="authory OAuth2 Url"></li>
                <li><input id="auth_type" name="auth_type" type="text" placeholder="moodle auth type"></li>
                <li>
                    <select id="flow" name="flow">
                        <option value="code">Code</option>
                        <option value="implict">Implicit</option>
                        <option value="hybrid">Hybrid</option>
                        <option value="assertion">Assertion</option>
                    </select>
                </li>
            </ul>
            <div>
                <input type="submit" id="storeazp" name="storeazp" value="Add Authority">
            </div>
        </div>
    </div>
</div>
