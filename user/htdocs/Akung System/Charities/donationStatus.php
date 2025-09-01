<form action="<?php echo $_SERVER['PHP_SELF'];?>" method='get'>
        <input type="text" name='search-text'value=<?php echo isset($_REQUEST['search'])?$_REQUEST['search-text']:"";?>>
        <input type="submit" name='search' value='Search'>
    </form>
    <table border="1">
        <tr>
            <td>No.</td>
            <td>Student</td>
            <td>Course</td>
            <td>Registration Date</td>
            <td colspan="2">Action</td>
        </tr>
        <?php

          
          $sql ="SELECT enroll.id as eid,
          enroll.reg_date,
          course.`code` as course,
          student.`name` as student
          FROM
          enroll
          INNER JOIN course ON enroll.course = course.id
          INNER JOIN student ON enroll.student = student.id
          ";
        if(isset($_GET['search']))
        {
            $search = $_GET['search-text'];

            $sql .= "WHERE  student.`name` LIKE '%$search%' or
            course.`code` LIKE '%$search%'";
        }

          $res =mysqli_query($con, $sql) or die("error". mysqli_error($con));
          $i=1;
          while ($row = mysqli_fetch_object($res)){
            echo "<tr>";
            echo "<td>".$i++."</td>";
            echo "<td>".$row->student."</td>";
            echo "<td>".$row->course."</td>";
            echo "<td>".$row->reg_date."</td>";


            echo "\n<form action='functions.php' method='post'>";
            echo "\n<input type='hidden' name='item-enroll' value='$row->eid'>";
            echo "\n   <td><input type='submit' name='delete-enroll' value='Delete' onclick=\"return confirm('Are you sure you want to unenroll $row->student?')\"></td>";
            echo "\n <td><a href = 'edit-enroll.php?edit-enroll=$row->eid' onclick=\"return confirm('Are you sure you want to edit the record')\">EDIT</a></td>";
            echo "\n</form>";
            echo "</tr>";
          }
        ?>
        <tr>
        </tr>
        
    </table>