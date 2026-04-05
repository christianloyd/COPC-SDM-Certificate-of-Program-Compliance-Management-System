/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./admin/**/*.php",
    "./includes/**/*.php",
    "./assets/js/**/*.js"
  ],
  theme: {
    extend: {
      colors: {
        prcnavy: '#0f2b5b',
        prcgold: '#c5a059',
        prclight: '#f8fafc',
        prcaccent: '#184283'
      },
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
      },
      boxShadow: {
        'soft': '0 10px 40px -10px rgba(15, 43, 91, 0.08)',
        'soft-lg': '0 20px 40px -10px rgba(15, 43, 91, 0.12)',
      }
    }
  },
  plugins: [],
}
