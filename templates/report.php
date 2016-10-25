
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Записались</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style type="text/css">
        h1 {
            margin-left: 0;
            font-size: 4em;
        }
        h2 {
            margin-left: 20px;
            font-size: 3.5em;
        }
        h3 {
            margin-left: 40px;
            font-size: 3em;
        }
        h4 {
            margin-left: 60px;
            font-size: 2.5em;
        }
        h5 {
            margin-left: 100px;
            font-size: 2em;
        }
        table.user-orders {
            margin-left: 100px;
        }
    </style>

</head>
<body>
<div class="container-fluid">

    <?php foreach ($ordersTree as $dateGroup): ?>

        <h1><?=mb_strtoupper(strftime('%a', strtotime($dateGroup['name'])));?> (<?=$dateGroup['count']?>)</h1>

        <?php foreach ($dateGroup['subGroups'] as $companyGroup): ?>
            <h2><?=$this->e($companyGroup['name']);?> (<?=$companyGroup['count']?>)</h2>

            <?php foreach ($companyGroup['subGroups'] as $addressGroup): ?>
                <h3><?=$this->e($addressGroup['name']);?> (<?=$addressGroup['count']?>)</h3>

                <?php foreach ($addressGroup['subGroups'] as $menuTypeGroup): ?>
                    <h4><?=$this->e($menuTypeGroup['name']);?> (<?=$menuTypeGroup['count']?>)</h4>

                    <?php foreach ($menuTypeGroup['subGroups'] as $orderStrGroup): ?>
                        <h5><?=$this->e($orderStrGroup['name']);?> (<?=$orderStrGroup['count']?>)</h5>

                        <table class="table table-striped user-orders">

                        <?php foreach ($orderStrGroup['orders'] as $order): ?>
                            <tr>
                                <td><?=$this->e($order->user()['fullname']);?></td>
                            </tr>
                        <?php endforeach; ?>

                        </table>
                    <?php endforeach; ?>

                <?php endforeach; ?>


            <?php endforeach; ?>

        <?php endforeach; ?>
        <hr>

    <?php endforeach; ?>

</div>

<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
</body>
</html>
