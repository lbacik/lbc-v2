import { Controller } from '@hotwired/stimulus'
import CanvasDrawer from "../gls/infrastructure/canvas-drawer.js";
import Gls from "../gls/gls/gls.js";

/*
* The following line makes this controller "lazy": it won't be downloaded until needed
* See https://github.com/symfony/stimulus-bridge#lazy-controllers
*/
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['canvas']

    gls = null

    connect() {
        this.canvasTarget.getContext('2d').scale(4,2)
        this.canvasTarget.getContext('2d').translate(-110, -40)

        this.calculateCanvasScale()

        fetch(this.element.dataset.glsData)
            .then(response => response.json())
            .then(data => this.render(data))

        let vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
        console.log('viewport height: ', vh)
    }

    render(glsData) {
        const drawer = new CanvasDrawer(this.canvasTarget)
        this.gls = new Gls(glsData, drawer)
        window.requestAnimationFrame(() => this.animate())
    }

    animate() {
        this.calculateCanvasScale()
        this.gls.step()

        setTimeout(
            () => window.requestAnimationFrame(() => this.animate()),
            25
        )
    }

    calculateCanvasScale() {
        const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        const vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);

        if (vh < 500 && vw < 800) {
            this.element.classList.add('d-none')
        } else {
            this.element.classList.remove('d-none')
        }
    }
}
