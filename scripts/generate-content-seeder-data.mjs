import { ALL_PRODUCTS, ALL_BLOGS } from '../src/data/initialData.js';
import { slugify } from '../src/seo/routeRegistry.js';
import fs from 'fs';
import path from 'path';

console.log(`Extracted ${ALL_PRODUCTS.length} products and ${ALL_BLOGS.length} blogs from src/data/initialData.js`);

const phpProducts = ALL_PRODUCTS.map(p => {
  const aff = p.affiliateLinks || {};
  return {
    id: p.id,
    title: p.title,
    category: p.category,
    price: p.price,
    rating: typeof p.rating === 'number' ? p.rating : parseFloat(p.rating || '4.5'),
    image: p.image,
    summary: p.shortDesc || p.summary || p.fullReview || '',
    pros: JSON.stringify(p.pros || []),
    cons: JSON.stringify(p.cons || []),
    affiliate_amazon: aff.amazon || '',
    affiliate_bestbuy: aff.bestbuy || '',
    affiliate_official: aff.official || ''
  };
});

const phpBlogs = ALL_BLOGS.map(b => {
  return {
    id: b.id,
    title: b.title,
    slug: slugify(b.title),
    category: b.category,
    image: b.image,
    summary: b.summary || '',
    content: b.content || '',
    author: b.author || 'Omar Abobakar',
    status: 'draft'
  };
});

console.log('Sample PHP product ID:', phpProducts[0].id, 'Title:', phpProducts[0].title);
console.log('Sample PHP blog ID:', phpBlogs[0].id, 'Slug:', phpBlogs[0].slug);

export { ALL_PRODUCTS, ALL_BLOGS, phpProducts, phpBlogs };
