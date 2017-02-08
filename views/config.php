
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
        <input type="submit" id="gen_key" name="gen_key" value="Generate Private Key"/>
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
                <p><input id="client_id" name="client_id" type="text" placeholder="client_id (as provided by the authority)"></p>
                <p><input id="url" name="url" type="text" placeholder="authory OAuth2 Url"></p>
                <input type="hidden" id="flow" name="flow" value="code"/>
        </div>
    </div>
</div>
