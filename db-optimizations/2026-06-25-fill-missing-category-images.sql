-- Fill missing OpenCart category images from products in that category.
-- It prefers products directly assigned to the category, then products from subcategories.
-- Review the SELECT first, then run the UPDATE.

SELECT
    c.category_id,
    cd.name,
    c.image AS current_image,
    (
        SELECT p.image
        FROM `oc_category_path` cp
        INNER JOIN `oc_product_to_category` ptc
            ON ptc.category_id = cp.category_id
        INNER JOIN `oc_product` p
            ON p.product_id = ptc.product_id
        WHERE cp.path_id = c.category_id
          AND p.status = 1
          AND p.image IS NOT NULL
          AND TRIM(p.image) <> ''
        ORDER BY
            CASE WHEN cp.category_id = c.category_id THEN 0 ELSE 1 END,
            p.sort_order ASC,
            p.product_id ASC
        LIMIT 1
    ) AS new_image
FROM `oc_category` c
LEFT JOIN `oc_category_description` cd
    ON cd.category_id = c.category_id
   AND cd.language_id = 3
WHERE (c.image IS NULL OR TRIM(c.image) = '' OR c.image = 'no_image.png')
HAVING new_image IS NOT NULL
ORDER BY c.category_id;

UPDATE `oc_category` c
SET
    c.image = (
        SELECT p.image
        FROM `oc_category_path` cp
        INNER JOIN `oc_product_to_category` ptc
            ON ptc.category_id = cp.category_id
        INNER JOIN `oc_product` p
            ON p.product_id = ptc.product_id
        WHERE cp.path_id = c.category_id
          AND p.status = 1
          AND p.image IS NOT NULL
          AND TRIM(p.image) <> ''
        ORDER BY
            CASE WHEN cp.category_id = c.category_id THEN 0 ELSE 1 END,
            p.sort_order ASC,
            p.product_id ASC
        LIMIT 1
    ),
    c.date_modified = NOW()
WHERE (c.image IS NULL OR TRIM(c.image) = '' OR c.image = 'no_image.png')
  AND EXISTS (
      SELECT 1
      FROM `oc_category_path` cp
      INNER JOIN `oc_product_to_category` ptc
          ON ptc.category_id = cp.category_id
      INNER JOIN `oc_product` p
          ON p.product_id = ptc.product_id
      WHERE cp.path_id = c.category_id
        AND p.status = 1
        AND p.image IS NOT NULL
        AND TRIM(p.image) <> ''
      LIMIT 1
  );
