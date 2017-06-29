<html>
<head>
<style>
form{width:700px; margin-left:auto; margin-right:auto; position:relative; text-align:center;}
.button{width:100%;}
textarea{width:100%; min-height:200px;}
.results{width:700px;margin-left:auto; margin-right:auto;}
</style>
</head>
<body>

<form method="POST">

<h1>JF2 Validator</h1>

<textarea name="data" placeholder="jf2 data here"><?php echo $_POST['data']?></textarea>
<br>
<label for="fix_quotes">Auto Convert all single quotes to double quotes: </label><input id="fix_quotes" type="checkbox" name="fix_quotes" value="true"  <?php echo (isset($_POST['fix_quotes']) ? 'checked="checked"' : '') ?>/><br>
<input class="button" type="submit" name="submit" value="Validate"/>

</form>

<div class="results">
<?php
if(isset($_POST['data'])){

    require_once __DIR__ . '/validator.php';

    $input = $_POST['data'];

    $fix_quotes = isset($_POST['fix_quotes']);

    $results = do_validate($input, $fix_quotes);

    $success = true;
    foreach($results as $result){
        $type = 'warn';
        if($result->type == P_ERROR){
            $success = false;
            $type = 'error';
        }
        echo '<div class="result '.$type.'>';
        echo $result->message ;
        echo '</div>';
    }
}
?>
</div>


</body>
</html>


