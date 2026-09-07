import type { Config } from 'tailwindcss';

const config: Config = {
  content: ['./src/**/*.{ts,tsx,js,jsx,mdx}'],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#eef4ff', 100: '#dbe6fe', 500: '#3056d3',
          600: '#2545b8', 700: '#1e3a9c', 900: '#14265e',
        },
      },
    },
  },
  plugins: [],
};
export default config;
