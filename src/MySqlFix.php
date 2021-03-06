<?php
//----------------------------------------------------------------------------------------------------------------------
namespace SetBased\ErdConcepts;

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for fixing issues in SQL code generated by ERD Concepts with MySQL as target database.
 */
class MySqlFix
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Maximum length of column comments in MySQL.
   */
  const MAX_COLUMN_COMMENT_LENGTH = 1024;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Add comments to column definitions based on commented comments.
   *
   * @param string $theSourceCode The SQL code generated by ERD concepts.
   *
   * @return string
   */
  public static function fixColumnComments($theSourceCode)
  {
    $source_lines = explode("\n", $theSourceCode);

    // Map from (table_name,column_name) to line number
    $map = [];

    // Scan the source for column definitions.
    $table_name = null;
    foreach ($source_lines as $i => $line)
    {
      if (isset($table_name))
      {
        if (preg_match('/^  `(\w+)`/', $source_lines[$i], $matches))
        {
          $map[$table_name][$matches[1]] = $i;
        }
        else
        {
          $table_name = null;
        }
      }

      if ($table_name===null && preg_match('/^CREATE TABLE `(\w+)`/', $line, $matches))
      {
        $table_name = $matches[1];
      }
    }

    // Scan the source for comments.
    $comments = [];
    foreach ($source_lines as $i => $line)
    {
      if (preg_match('/^COMMENT ON COLUMN `(\w+)`.`(\w+)`/', $line, $matches))
      {
        $comments[$matches[1]][$matches[2]] = trim($source_lines[$i + 1]);
      }
    }

    // Enhance the column definitions with comments.
    foreach ($comments as $table_name => $columns)
    {
      if (!isset($map[$table_name]))
      {
        throw new \RuntimeException(sprintf("Table '%s' is not defined.", $table_name));
      }

      foreach ($columns as $column_name => $comment)
      {
        if (!isset($map[$table_name][$column_name]))
        {
          throw new \RuntimeException(sprintf("Column '%s' is not defined in '%s' table statements.",
                                              $column_name,
                                              $table_name));
        }

        $line_number = $map[$table_name][$column_name];

        // Truncate comments longer than 60 characters.
        if (strlen($comment)>self::MAX_COLUMN_COMMENT_LENGTH)
        {
          $comment = trim(mb_substr($comment, 0, self::MAX_COLUMN_COMMENT_LENGTH - 3)).'...';
        }

        // Enhance the column definition with comment.
        $source_lines[$line_number] = mb_substr(rtrim($source_lines[$line_number]), 0, -1);
        $source_lines[$line_number] .= " COMMENT '".self::escapeMysqlString($comment)."',";
      }
    }

    $new_source_code = implode("\n", $source_lines);

    return $new_source_code;
  }


  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Add comments to index definitions based on commented comments.
   *
   * @param string $theSourceCode The SQL code generated by ERD concepts.
   *
   * @return string
   */
  public static function fixIndexComments($theSourceCode)
  {
    $source_lines = explode("\n", $theSourceCode);

    // Map from (table_name,column_name) to line number
    $map = [];

    // Scan the source for column definitions.
    $index_name = null;
    foreach ($source_lines as $i => $line)
    {
      if (preg_match('/^CREATE INDEX `(\w+)`(\s*\()?/', $line, $matches))
      {
        $map[$matches[1]] = $i;
      }
    }

    // Scan the source for comments.
    $comments = [];
    foreach ($source_lines as $i => $line)
    {
      if (preg_match('/^COMMENT ON INDEX `(\w+)`/', $line, $matches))
      {
        $comments[$matches[1]] = trim($source_lines[$i + 1]);
      }
    }

    // Enhance the column definitions with comments.
    foreach ($comments as $index_name => $comment)
    {
      if (!isset($map[$index_name]))
      {
        throw new \RuntimeException(sprintf("Table '%s' is not defined.", $index_name));
      }

      $line_number = $map[$index_name];

      // Truncate comments longer than 60 characters.
      if (strlen($comment)>self::MAX_COLUMN_COMMENT_LENGTH)
      {
        $comment = trim(mb_substr($comment, 0, self::MAX_COLUMN_COMMENT_LENGTH - 3)).'...';
      }

      // Enhance the column definition with comment.
      $source_lines[$line_number] = mb_substr(rtrim($source_lines[$line_number]), 0, -1);
      $source_lines[$line_number] .= " COMMENT '".self::escapeMysqlString($comment)."';";
    }

    $new_source_code = implode("\n", $source_lines);


    return $new_source_code;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Add comments to table definitions based on commented comments.
   *
   * @param string $theSourceCode The SQL code generated by ERD concepts.
   *
   * @return string
   */
  public static function fixTableComments($theSourceCode)
  {
    $source_lines = explode("\n", $theSourceCode);

    // Map from (table_name,column_name) to line number
    $map = [];

    // Scan the source for column definitions.
    $table_name = null;
    $level      = 0;
    foreach ($source_lines as $i => $line)
    {
      if (isset($table_name))
      {
        if (preg_match('/\)|\(/', $source_lines[$i], $matches))
        {
          if ($matches[0]=='(') $level = +1;
          if ($matches[0]==')') $level = -1;

          if ($level<0)
          {
            $map[$table_name] = $i;
            $table_name       = null;
          }
        }
      }

      if ($table_name===null && preg_match('/^CREATE TABLE `(\w+)`(\s*\()?/', $line, $matches))
      {
        $table_name = $matches[1];
        if ($matches[2]) $level = 1;
      }
    }

    // Scan the source for comments.
    $comments = [];
    foreach ($source_lines as $i => $line)
    {
      if (preg_match('/^COMMENT ON TABLE `(\w+)`/', $line, $matches))
      {
        $comments[$matches[1]] = trim($source_lines[$i + 1]);
      }
    }

    // Enhance the column definitions with comments.
    foreach ($comments as $table_name => $comment)
    {
      if (!isset($map[$table_name]))
      {
        throw new \RuntimeException(sprintf("Table '%s' is not defined.", $table_name));
      }

      $line_number = $map[$table_name];

      // Truncate comments longer than 60 characters.
      if (strlen($comment)>self::MAX_COLUMN_COMMENT_LENGTH)
      {
        $comment = trim(mb_substr($comment, 0, self::MAX_COLUMN_COMMENT_LENGTH - 3)).'...';
      }

      // Enhance the column definition with comment.
      $source_lines[$line_number] = mb_substr(rtrim($source_lines[$line_number]), 0, -1);
      $source_lines[$line_number] .= " COMMENT '".self::escapeMysqlString($comment)."';";
    }

    $new_source_code = implode("\n", $source_lines);


    return $new_source_code;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Escapes special characters in a string for use in an SQL statement.
   *
   * @param string $unescaped_string The string that is to be escaped.
   *
   * @return string
   */
  protected static function escapeMysqlString($unescaped_string)
  {
    // We prefer to use mysqli::escape_string but this method requires a connection. Since ERD Concepts generates
    // SQL code in UTF-8 and $unescaped_string is not user input (from the evil internet) we can safely use addslashes.
    return addslashes($unescaped_string);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
