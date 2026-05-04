export const SITE = {
  name: 'Apriori Studio',
  legalName: 'Apriori Ltd',
  domain: 'aprioristudio.com',
  url: 'https://aprioristudio.com',
  tagline: 'Web & mobile, built from first principles.',
  description:
    'Apriori Studio is a UK software studio building modern websites and mobile apps end-to-end — from domain and hosting to design, development and launch.',
  email: {
    contact: 'mp@aprioristudio.com',
    nina: 'np@aprioristudio.com',
    noreply: 'noreply@aprioristudio.com',
  },
  address: {
    country: 'GB',
    region: 'England',
    locality: 'London',
  },
  social: {
    // Placeholders — fill when accounts exist
  },
} as const;

export type NavItem = { href: string; label: string };

export const NAV: NavItem[] = [
  { href: '/web', label: 'Websites' },
  { href: '/apps', label: 'Mobile apps' },
  { href: '/about', label: 'About' },
  { href: '/contact', label: 'Contact' },
];

export const WEB_SERVICES = [
  {
    href: '/web/ecommerce',
    title: 'E-commerce stores',
    blurb:
      'Storefronts that convert: Shopify, WooCommerce, or fully custom. Stripe, payments, inventory, multi-language.',
  },
  {
    href: '/web/corporate',
    title: 'Corporate & marketing sites',
    blurb:
      'Presentation sites, landing pages, content hubs. Fast, indexable by Google, built around your conversion goals.',
  },
  {
    href: '/web/saas',
    title: 'SaaS & web applications',
    blurb:
      'Custom dashboards, B2B tools, internal portals. Authentication, billing, role-based access, integrations.',
  },
] as const;

export const APP_SERVICES = [
  {
    href: '/apps/marketplace',
    title: 'Marketplace & e-commerce apps',
    blurb:
      'Buyer + seller flows, payments, listings, reviews, push notifications. iOS and Android from one design.',
  },
  {
    href: '/apps/on-demand',
    title: 'On-demand services',
    blurb:
      'Delivery, taxi, courier, professional services. Real-time location, dispatch, in-app payments, ratings.',
  },
  {
    href: '/apps/booking',
    title: 'Booking & reservation',
    blurb:
      'Restaurants, salons, hotels, fitness studios. Calendar, slots, reminders, payments, recurring bookings.',
  },
  {
    href: '/apps/fitness',
    title: 'Fitness & health',
    blurb:
      'Workouts, tracking, nutrition, telemedicine. HealthKit / Google Fit, wearables, secure data handling.',
  },
] as const;
