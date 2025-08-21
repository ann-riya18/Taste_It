<?php
// DB connection
$conn = new mysqli("localhost", "root", "", "tasteit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id = $_GET['id'] ?? 0;
$sql = "SELECT * FROM recipes WHERE id=$id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $title = htmlspecialchars($row['title']);
    $desc = nl2br(htmlspecialchars($row['description']));
    $steps = nl2br(htmlspecialchars($row['steps']));
    $ingredients = explode(",", $row['ingredients']);
    $img = $row['image_path'] ?: "img/placeholder.png";
} else {
    echo "Recipe not found.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo $title; ?> - TasteIt</title>
  <style>
    body {font-family: Arial, sans-serif; margin:0; padding:0; background:#f9f9f9;}
    .container {width:80%; margin:50px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1);}
    h1 {color:#333;}
    img {max-width:100%; border-radius:10px; margin-bottom:20px;}
    .section {margin-bottom:25px;}
    ul {list-style: disc; padding-left:20px;}
    ul li {margin:6px 0; font-size:16px; color:#444;}
    table {width:100%; border-collapse: collapse; margin-top:15px;}
    table th, table td {border:1px solid #ddd; padding:10px; text-align:left;}
    table th {background:#5A6E2D; color:#fff;}
    table td a {
        margin-right:8px;
        text-decoration:none; 
        padding:6px 10px; 
        border-radius:5px; 
        font-size:14px; 
        background:#5A6E2D; 
        color:#fff;
        display:inline-block;
    }
    table td a:hover {background:#3d4e1f;}
  </style>
</head>
<body>
  <div class="container">
    <h1><?php echo $title; ?></h1>
    <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>">

    <div class="section">
      <h2>Description</h2>
      <p><?php echo $desc; ?></p>
    </div>

    <div class="section">
      <h2>Ingredients</h2>
      <ul>
        <?php foreach ($ingredients as $ing): ?>
          <li><?php echo htmlspecialchars(trim($ing)); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="section">
      <h2>Steps</h2>
      <p><?php echo $steps; ?></p>
    </div>

    <div class="section">
      <h2>🛒 Buy Ingredients</h2>
      <table>
        <tr>
          <th>Ingredient</th>
          <th>Buy Links</th>
        </tr>
        <?php foreach ($ingredients as $ing): 
            $ing = trim($ing);
            $search = urlencode($ing);
        ?>
        <tr>
          <td><?php echo htmlspecialchars($ing); ?></td>
          <td>
            <a href="https://www.amazon.in/s?k=<?php echo $search; ?>&i=grocery" target="_blank">Amazon Fresh</a>
            <a href="https://www.bigbasket.com/ps/?q=<?php echo $search; ?>" target="_blank">BigBasket</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</body>
</html>
