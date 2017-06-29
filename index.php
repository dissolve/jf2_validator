<!DOCTYPE html>
<html>
  <head>
    <title>Validate JF2</title>
	<link href="style.css" rel="stylesheet">
  </head>
  <body>

    <div class="single-column">
        <h1>JF2 Validator</h1>
        <h2>WARNING: This validator is in active development, results may not be accurate.</h2>

<?php 
$input_data = filter_input(INPUT_POST, 'data' ); // removing actual filter from this for now
$fix_quotes = isset($_POST['fix_quotes']);
?>
    <section class="content">
        <form method="POST">

          <textarea name="data" placeholder="jf2 data here"><?php echo htmlspecialchars($input_data) ?></textarea>
          <br>
          <label for="fix_quotes">Auto Convert all single quotes to double quotes: </label><input id="fix_quotes" type="checkbox" name="fix_quotes" value="true"  <?php echo ($fix_quotes ? 'checked="checked"' : '') ?>/><br>
          <input class="button" type="submit" name="submit" value="Validate"/>

        </form>
    </section>

    <?php
    if($input_data){

        require_once __DIR__ . '/validator.php';

        $validator = new JF2Validator();
        $results = $validator->validate($input_data, $fix_quotes);

        $success = true;
        foreach($results as $result){
            $type = 'warn';
            if($result->type == P_ERROR){
                $success = false;
                $type = 'error';
            }
            echo '<section class="content result '.$type.'">';
            echo $result->message ;
            echo '</section>';
        }
    }

    if(isset($success) && $success){
        echo '<section class=" content result success">This validator is not yet complete, but theres no errors in it thus far.</section>';
    }

    ?>

    <div class="small">
        This validator is open source and available on <a href="https://github.com/dissolve/jf2_validator">GitHub</a>
    <div>

    </div>



</body>
</html>


