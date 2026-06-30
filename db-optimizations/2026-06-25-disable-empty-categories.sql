-- Disable OpenCart categories that have no active products in the category
-- or any of its subcategories.
--
-- Review the SELECT first, then run the UPDATE.
-- Change @salona_store_id if the live store is not store_id 0.

SET @salona_store_id := 0;

SELECT
    c.category_id,
    cd.name,
    c.status
FROM `oc_category` c
LEFT JOIN `oc_category_description` cd
    ON cd.category_id = c.category_id
   AND cd.language_id = 3
WHERE c.status = 1
  AND NOT EXISTS (
      SELECT 1
      FROM `oc_category_path` cp
      INNER JOIN `oc_product_to_category` ptc
          ON ptc.category_id = cp.category_id
      INNER JOIN `oc_product` p
          ON p.product_id = ptc.product_id
      INNER JOIN `oc_product_to_store` pts
          ON pts.product_id = p.product_id
         AND pts.store_id = @salona_store_id
      WHERE cp.path_id = c.category_id
        AND p.status = 1
      LIMIT 1
  )
ORDER BY c.category_id;

UPDATE `oc_category` c
SET
    c.status = 0,
    c.date_modified = NOW()
WHERE c.status = 1
  AND NOT EXISTS (
      SELECT 1
      FROM `oc_category_path` cp
      INNER JOIN `oc_product_to_category` ptc
          ON ptc.category_id = cp.category_id
      INNER JOIN `oc_product` p
          ON p.product_id = ptc.product_id
      INNER JOIN `oc_product_to_store` pts
          ON pts.product_id = p.product_id
         AND pts.store_id = @salona_store_id
      WHERE cp.path_id = c.category_id
        AND p.status = 1
      LIMIT 1
  );
