import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.176.0/build/three.module.min.js';

const canvas = document.getElementById('hero-shader');
if (!canvas) throw new Error('hero-shader not found');

const hero  = canvas.parentElement;
const scene = new THREE.Scene();
const camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, -1);

const renderer = new THREE.WebGLRenderer({ canvas, alpha: false, antialias: false });
renderer.setPixelRatio(1); // keep 1:1 so gl_FragCoord matches resolution uniform
renderer.setClearColor(0x000000);

const uniforms = {
  resolution: { value: new THREE.Vector2() },
  time:       { value: 0 },
  xScale:     { value: 1.0 },
  yScale:     { value: 0.5 },
  distortion: { value: 0.05 },
};

const geometry = new THREE.BufferGeometry();
geometry.setAttribute('position', new THREE.BufferAttribute(new Float32Array([
  -1,-1,0,  1,-1,0, -1,1,0,
   1,-1,0, -1, 1,0,  1,1,0,
]), 3));

scene.add(new THREE.Mesh(geometry, new THREE.RawShaderMaterial({
  vertexShader: `
    attribute vec3 position;
    void main() { gl_Position = vec4(position, 1.0); }
  `,
  fragmentShader: `
    precision highp float;
    uniform vec2 resolution;
    uniform float time;
    uniform float xScale;
    uniform float yScale;
    uniform float distortion;
    void main() {
      vec2 p = (gl_FragCoord.xy * 2.0 - resolution) / min(resolution.x, resolution.y);
      float d = length(p) * distortion;
      float rx = p.x * (1.0 + d);
      float gx = p.x;
      float bx = p.x * (1.0 - d);
      float r = 0.05 / abs(p.y + sin((rx + time) * xScale) * yScale);
      float g = 0.05 / abs(p.y + sin((gx + time) * xScale) * yScale);
      float b = 0.05 / abs(p.y + sin((bx + time) * xScale) * yScale);
      gl_FragColor = vec4(r, g, b, 1.0);
    }
  `,
  uniforms,
  side: THREE.DoubleSide,
})));

function resize() {
  const w = hero.offsetWidth;
  const h = hero.offsetHeight;
  renderer.setSize(w, h, false);
  uniforms.resolution.value.set(w, h);
}
resize();
new ResizeObserver(resize).observe(hero);

(function animate() {
  uniforms.time.value += 0.01;
  renderer.render(scene, camera);
  requestAnimationFrame(animate);
})();
