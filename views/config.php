
<div id="settings">
    <!-- global private key settings -->
    <div id="private_key">
<?php
if (isset($PK)) {
    echo "<div>" . get_string('private_key_present', 'auth_oauth2') . "</div>";
}
?>
    <div>
        <textarea id="pk" name="pk"></textarea>
    </div>
    <div>
        <input type="submit" id="gen_key" name="gen_key" value="<?php echo get_string('generate_private_key', 'auth_oauth2'); ?>"/>
    </div>
    </div>
    <!-- list of authorities -->
    <div id="authorityList">
        <h2><?php echo get_string('registered_auth_services', 'auth_oauth2'); ?></h2>
        <ul>
            <?php
foreach ($authorities as $azp) {
    echo '<li><a href="'.$azpurl . $azp->id.'">'.$azp->name.'</a></li>';
}
            ?>
        </ul>
        <div>
                <p>
                    <?php echo get_string('oauth2_redirect_uri_is', 'auth_oauth2'); ?> <pre><?php echo "$tlaurl/cb"?></pre>
                </p>
                <p>
                    <?php echo get_string('public_key_is', 'auth_oauth2'); ?>
                </p>
                <p><?php echo $pubKey; ?></p>
                <p><input id="name" name="name" type="text" placeholder="<?php echo get_string('authority_name', 'auth_oauth2'); ?>"></p>
                <p><input id="client_id" name="client_id" type="text" placeholder="client_id (as provided by the authority)"></p>
                <p><input id="url" name="url" type="text" placeholder="authory OAuth2 Url"></p>
                <input type="hidden" id="flow" name="flow" value="code"/>
        </div>
    </div>
</div>
