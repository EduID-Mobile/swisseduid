
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
    </div>
    <!-- list of authorities -->
    <div id="authorityList">
        <h2>Registered Authorization Services</h2>
        <ul>
            <?php
foreach ($authorities as $azp) {
    echo '<li><a href="'.$azpurl . $azp->id.'">'.$azp->name.'</a></li>';
}
            ?>
        </ul>
        <div>
                <p>
                    Your OAuth2 redirect_uri is <pre><?php echo "$tlaurl/cb"?></pre>
                </p>
                <p>
                    Your Public Key is:
                </p>
                <p><?php echo $pubKey; ?></p>
                <p><input id="name" name="name" type="text" placeholder="Authority Name"></p>
                <p><input id="iss" name="iss" type="text" placeholder="Issuer Value (as provided by the authority)"></p>
                <p><input id="client_id" name="client_id" type="text" placeholder="client_id (as provided by the authority)"></p>
                <p><input id="url" name="url" type="text" placeholder="authory OAuth2 Url"></p>
                <p><input id="auth_type" name="auth_type" type="text" placeholder="moodle auth type"></p>
                <p>
                    <select id="flow" name="flow">
                        <option value="code">Code</option>
                        <option value="implict">Implicit</option>
                        <option value="hybrid">Hybrid</option>
                        <option value="assertion">Assertion</option>
                    </select>
                </p>
        </div>
    </div>
</div>
