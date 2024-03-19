import AbstractDrawer from "../gls/abstract-drawer.js";

class CanvasDrawer extends AbstractDrawer {
  constructor(canvas) {
    super()
    this.canvas = canvas
    this.context = canvas.getContext('2d')
  }

  drawPoint(point, color = 'black', width = 1) {
    this.context.beginPath()
    this.context.strokeStyle = color
    this.context.lineWidth = width
    this.context.arc(point[0], point[1], 1, 0, 2 * Math.PI, true)
    this.context.stroke()
  }

  drawLine(pointA, pointB, color = 'black', width = 1) {
    this.context.beginPath()
    this.context.strokeStyle = color
    this.context.lineWidth = width
    this.context.moveTo(pointA[0], pointA[1])
    const targetPoint = this.calculateTargetPointWithCorrection(pointA, pointB, width)
    this.context.lineTo(targetPoint[0], targetPoint[1])
    this.context.stroke()
  }

  clear() {
    this.context.clearRect(0, 0, this.canvas.width, this.canvas.height)
  }

  calculateTargetPointWithCorrection(pointA, pointB, width) {
    const additionalLength = Math.floor(width / 2)
    const direction = this.calculateDirection(pointA, pointB)
    return this.addCorrection(pointB, direction, additionalLength)
  }

  calculateDirection(pointA, pointB) {
    const direction = [0, 0]
    direction[0] = pointB[0] - pointA[0]
    direction[1] = pointB[1] - pointA[1]
    return direction
  }

  addCorrection(point, direction, additionalLength) {
    const newPoint = point.slice()

    if (direction[0] > 0) {
      newPoint[0] += additionalLength
    } else if (direction[0] < 0) {
      newPoint[0] -= additionalLength
    } else if (direction[1] > 0) {
      newPoint[1] += additionalLength
    } else if (direction[1] < 0) {
      newPoint[1] -= additionalLength
    }

    return newPoint
  }
}

// module.exports = CanvasDrawer

export default CanvasDrawer
